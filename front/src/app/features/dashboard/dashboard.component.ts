import { Component, inject, signal, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/services/api.service';
import { AdminService, ActiveUsersMetrics } from '../../core/services/admin.service';
import { AuthService } from '../../core/services/auth.service';
import { TokenService } from '../../core/services/token.service';
import { MercureService, MercureMessage } from '../../core/services/mercure.service';
import { ReportChatService, ReportThread, ReportMessage as ReportChatMessage } from '../../core/services/report-chat.service';
import { ToastService } from '../../core/services/toast.service';
import {
  DashboardResponse,
  AdminUser,
  RoleDefinition,
  PaginationInfo,
  AdminUserFilters,
  AccountActionRequest,
  AuditLogResponse,
  AuditAction,
} from '../../core/models';

interface AccordionSection {
  id: string;
  title: string;
  icon: string;
  isOpen: boolean;
  badge?: number;
}

type UserStatus = 'active' | 'suspended' | 'deleted' | 'unverified' | '';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent implements OnInit, OnDestroy {
  private readonly apiService = inject(ApiService);
  private readonly adminService = inject(AdminService);
  private readonly authService = inject(AuthService);
  private readonly tokenService = inject(TokenService);
  private readonly mercureService = inject(MercureService);
  private readonly reportChatService = inject(ReportChatService);
  private readonly toastService = inject(ToastService);

  // Dashboard data
  dashboardData = signal<DashboardResponse | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  currentUser = this.authService.currentUser;
  isAdmin = this.authService.isAdmin;
  isSuperAdmin = this.authService.isSuperAdmin;

  // Accordion sections
  accordionSections = signal<AccordionSection[]>([
    { id: 'users', title: 'Gestion des utilisateurs', icon: 'users', isOpen: false },
    { id: 'delete-requests', title: 'Demandes de suppression', icon: 'trash', isOpen: false, badge: 0 },
    { id: 'reports', title: 'Rapports utilisateurs', icon: 'chat', isOpen: false, badge: 0 },
    { id: 'audit-logs', title: 'Journal d\'audit', icon: 'clipboard', isOpen: false },
  ]);

  // Users management
  users = signal<AdminUser[]>([]);
  usersLoading = signal(false);
  usersError = signal<string | null>(null);
  searchQuery = signal('');
  statusFilter = signal<UserStatus>('');
  roleFilter = signal('');
  sortBy = signal<'email' | 'dateInscription' | 'dateDerniereConnexion' | 'nomComplet'>('dateInscription');
  sortOrder = signal<'asc' | 'desc'>('desc');
  pagination = signal<PaginationInfo>({ page: 1, limit: 10, total: 0, totalPages: 0 });

  // Roles management
  availableRoles = signal<RoleDefinition[]>([]);
  editingUserId = signal<number | null>(null);
  editingRoles = signal<string[]>([]);
  savingRoles = signal(false);

  // User actions
  actionLoading = signal<number | null>(null);
  confirmAction = signal<{ type: string; user: AdminUser } | null>(null);

  // Suspension modal
  suspendModal = signal<{ user: AdminUser; reason: string; until: string } | null>(null);

  // Delete requests
  deleteRequests = signal<AccountActionRequest[]>([]);
  deleteRequestsLoading = signal(false);
  deleteRequestsPagination = signal<PaginationInfo>({ page: 1, limit: 10, total: 0, totalPages: 0 });

  // Impersonation
  impersonationReason = signal('');

  // User detail modal
  selectedUser = signal<AdminUser | null>(null);

  // Real-time active users (from Mercure)
  activeUsersCount = this.mercureService.activeUsersCount;

  // Report chat
  reports = signal<ReportThread[]>([]);
  reportsLoading = signal(false);
  unreadReportsCount = this.mercureService.unreadReportsCount;
  selectedReport = signal<ReportThread | null>(null);
  reportMessages = signal<ReportChatMessage[]>([]);
  reportMessagesLoading = signal(false);
  newReportMessage = signal('');
  sendingMessage = signal(false);

  // Audit logs
  auditLogs = signal<AuditLogResponse[]>([]);
  auditLogsLoading = signal(false);
  auditLogsPagination = signal<PaginationInfo>({ page: 1, limit: 20, total: 0, totalPages: 0 });
  auditActions = signal<AuditAction[]>([]);
  auditActionFilter = signal('');
  auditSearchQuery = signal('');

  // Audit detail modal
  selectedAuditLog = signal<AuditLogResponse | null>(null);

  // Polling fallback for active users
  private activeUsersPollingInterval: ReturnType<typeof setInterval> | null = null;
  private readonly POLLING_INTERVAL_MS = 30000; // 30 seconds

  ngOnInit(): void {
    this.loadDashboard();
    if (this.isAdmin()) {
      this.loadAvailableRoles();
      this.setupMercureSubscriptions();
      this.loadReports();
      this.setupActiveUsersPollingFallback();
    }
  }

  ngOnDestroy(): void {
    this.mercureService.closeAll();
    this.stopActiveUsersPolling();
  }

  /**
   * Setup polling fallback for active users when Mercure is not connected.
   */
  private setupActiveUsersPollingFallback(): void {
    // Check every 5 seconds if Mercure is connected
    // If not connected, poll the API for active users
    this.activeUsersPollingInterval = setInterval(() => {
      if (!this.mercureService.isConnected()) {
        this.pollActiveUsers();
      }
    }, this.POLLING_INTERVAL_MS);

    // Initial poll
    setTimeout(() => {
      if (!this.mercureService.isConnected()) {
        this.pollActiveUsers();
      }
    }, 2000);
  }

  private stopActiveUsersPolling(): void {
    if (this.activeUsersPollingInterval) {
      clearInterval(this.activeUsersPollingInterval);
      this.activeUsersPollingInterval = null;
    }
  }

  private pollActiveUsers(): void {
    this.adminService.getActiveUsersMetrics().subscribe({
      next: (metrics) => {
        // Update the signal directly
        this.mercureService.activeUsersCount.set(metrics.activeSessions);
      },
      error: () => {
        // Silently fail - we'll retry on next interval
      }
    });
  }

  private setupMercureSubscriptions(): void {
    // Subscribe to active users updates
    this.mercureService.subscribeToActiveUsers();

    // Subscribe to new report messages
    this.mercureService.subscribeToAdminReports((data) => {
      const sender = data.sender;
      this.toastService.info(
        'Nouveau rapport',
        sender?.email
          ? `${sender.nomComplet || sender.email} a envoyé un message.`
          : 'Nouveau message reçu.'
      );

      // Update reports badge in accordion
      this.accordionSections.update(sections =>
        sections.map(s => s.id === 'reports'
          ? { ...s, badge: (s.badge || 0) + 1 }
          : s
        )
      );

      // Reload reports if the section is open
      if (this.isSectionOpen('reports')) {
        this.loadReports();
      }

      // If viewing the specific thread, refresh messages
      const selected = this.selectedReport();
      if (selected && selected.id === data.threadId) {
        this.loadReportMessages(selected.id);
      }
    });

    // Subscribe to admin notifications
    this.mercureService.subscribeToAdminNotifications((data) => {
      const notification = data['notification'] as { title: string; message: string; type: string } | undefined;
      if (notification) {
        const toastType = (notification.type || 'info') as 'success' | 'error' | 'warning' | 'info';
        this.toastService.show(toastType, notification.title, notification.message);
      }
    });

    // Subscribe to dashboard stats updates
    this.mercureService.subscribeToDashboardStats((stats) => {
      this.dashboardData.update(data => {
        if (!data) return data;
        return {
          ...data,
          stats: {
            ...data.stats,
            ...stats as Partial<typeof data.stats>
          }
        };
      });
    });
  }

  loadDashboard(): void {
    this.loading.set(true);
    this.error.set(null);

    this.apiService.getDashboard().subscribe({
      next: (data) => {
        this.dashboardData.set(data);
        this.loading.set(false);
        // Update delete requests badge
        if (data.stats?.pendingDeleteRequests) {
          this.accordionSections.update(sections =>
            sections.map(s => s.id === 'delete-requests' ? { ...s, badge: data.stats.pendingDeleteRequests } : s)
          );
        }
      },
      error: (err) => {
        this.error.set(err.message || 'Erreur lors du chargement du dashboard');
        this.loading.set(false);
      }
    });
  }

  refresh(): void {
    this.loadDashboard();
  }

  // === ACCORDION ===

  toggleSection(sectionId: string): void {
    this.accordionSections.update(sections =>
      sections.map(s => ({
        ...s,
        isOpen: s.id === sectionId ? !s.isOpen : s.isOpen
      }))
    );

    const section = this.accordionSections().find(s => s.id === sectionId);
    if (section?.isOpen) {
      if (sectionId === 'users' && this.users().length === 0) {
        this.loadUsers();
      } else if (sectionId === 'delete-requests' && this.deleteRequests().length === 0) {
        this.loadDeleteRequests();
      } else if (sectionId === 'reports' && this.reports().length === 0) {
        this.loadReports();
      } else if (sectionId === 'audit-logs' && this.auditLogs().length === 0) {
        this.loadAuditLogs();
        this.loadAuditActions();
      }
    }
  }

  isSectionOpen(sectionId: string): boolean {
    return this.accordionSections().find(s => s.id === sectionId)?.isOpen ?? false;
  }

  getSectionBadge(sectionId: string): number | undefined {
    return this.accordionSections().find(s => s.id === sectionId)?.badge;
  }

  // === USER MANAGEMENT ===

  loadUsers(page: number = 1): void {
    this.usersLoading.set(true);
    this.usersError.set(null);

    const filters: AdminUserFilters = {
      search: this.searchQuery() || undefined,
      status: this.statusFilter() || undefined,
      role: this.roleFilter() || undefined,
      sortBy: this.sortBy(),
      sortOrder: this.sortOrder(),
      page,
      limit: 10
    };

    this.adminService.getUsers(filters).subscribe({
      next: (response) => {
        this.users.set(response.users);
        this.pagination.set(response.pagination);
        this.usersLoading.set(false);
      },
      error: (err) => {
        this.usersError.set(err.message || 'Erreur lors du chargement des utilisateurs');
        this.usersLoading.set(false);
      }
    });
  }

  onSearch(): void {
    this.loadUsers(1);
  }

  onFilterChange(): void {
    this.loadUsers(1);
  }

  onSortChange(field: 'email' | 'dateInscription' | 'dateDerniereConnexion' | 'nomComplet'): void {
    if (this.sortBy() === field) {
      this.sortOrder.update(order => order === 'asc' ? 'desc' : 'asc');
    } else {
      this.sortBy.set(field);
      this.sortOrder.set('desc');
    }
    this.loadUsers(1);
  }

  onPageChange(page: number): void {
    this.loadUsers(page);
  }

  loadAvailableRoles(): void {
    this.adminService.getAvailableRoles().subscribe({
      next: (response) => this.availableRoles.set(response.roles),
      error: () => {}
    });
  }

  // === ROLE MANAGEMENT ===

  startEditRoles(user: AdminUser): void {
    this.editingUserId.set(user.id);
    this.editingRoles.set([...user.roles]);
  }

  cancelEditRoles(): void {
    this.editingUserId.set(null);
    this.editingRoles.set([]);
  }

  toggleRole(roleCode: string): void {
    this.editingRoles.update(roles => {
      if (roles.includes(roleCode)) {
        if (roleCode === 'ROLE_USER') return roles;
        return roles.filter(r => r !== roleCode);
      } else {
        return [...roles, roleCode];
      }
    });
  }

  isRoleSelected(roleCode: string): boolean {
    return this.editingRoles().includes(roleCode);
  }

  saveRoles(): void {
    const userId = this.editingUserId();
    if (!userId) return;

    this.savingRoles.set(true);

    // Filter out ROLE_SUPER_ADMIN if current user is not super admin
    let rolesToSave = this.editingRoles();
    if (!this.isSuperAdmin()) {
      rolesToSave = rolesToSave.filter(r => r !== 'ROLE_SUPER_ADMIN');
    }

    this.adminService.updateUserRoles(userId, rolesToSave).subscribe({
      next: (response) => {
        this.users.update(users =>
          users.map(u => u.id === userId ? response.user : u)
        );
        this.cancelEditRoles();
        this.savingRoles.set(false);
        this.toastService.success(
          'Rôles mis à jour',
          `Les rôles de ${response.user.email} ont été modifiés.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message || 'Erreur lors de la mise à jour des rôles');
        this.savingRoles.set(false);
      }
    });
  }

  isCurrentUser(userId: number): boolean {
    const currentEmail = this.currentUser()?.email;
    const user = this.users().find(u => u.id === userId);
    return user?.email === currentEmail;
  }

  // === USER ACTIONS ===

  suspendUser(user: AdminUser): void {
    this.suspendModal.set({ user, reason: '', until: '' });
  }

  confirmSuspend(): void {
    const modal = this.suspendModal();
    if (!modal) return;

    this.actionLoading.set(modal.user.id);

    this.adminService.suspendUser(modal.user.id, {
      reason: modal.reason || undefined,
      until: modal.until || undefined
    }).subscribe({
      next: (response) => {
        this.users.update(users =>
          users.map(u => u.id === modal.user.id ? response.user : u)
        );
        this.suspendModal.set(null);
        this.actionLoading.set(null);
        this.toastService.warning(
          'Utilisateur suspendu',
          `${response.user.email} a été suspendu.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message);
        this.actionLoading.set(null);
      }
    });
  }

  unsuspendUser(user: AdminUser): void {
    this.actionLoading.set(user.id);

    this.adminService.unsuspendUser(user.id).subscribe({
      next: (response) => {
        this.users.update(users =>
          users.map(u => u.id === user.id ? response.user : u)
        );
        this.actionLoading.set(null);
        this.toastService.success(
          'Suspension levée',
          `${response.user.email} n'est plus suspendu.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message);
        this.actionLoading.set(null);
      }
    });
  }

  softDeleteUser(user: AdminUser): void {
    this.confirmAction.set({ type: 'softDelete', user });
  }

  restoreUser(user: AdminUser): void {
    this.actionLoading.set(user.id);

    this.adminService.restoreUser(user.id).subscribe({
      next: (response) => {
        this.users.update(users =>
          users.map(u => u.id === user.id ? response.user : u)
        );
        this.actionLoading.set(null);
        this.toastService.success(
          'Utilisateur restauré',
          `${response.user.email} a été restauré.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message);
        this.actionLoading.set(null);
      }
    });
  }

  hardDeleteUser(user: AdminUser): void {
    this.confirmAction.set({ type: 'hardDelete', user });
  }

  executeConfirmedAction(): void {
    const action = this.confirmAction();
    if (!action) return;

    this.actionLoading.set(action.user.id);

    if (action.type === 'softDelete') {
      this.adminService.softDeleteUser(action.user.id).subscribe({
        next: (response) => {
          this.users.update(users =>
            users.map(u => u.id === action.user.id ? response.user : u)
          );
          this.confirmAction.set(null);
          this.actionLoading.set(null);
          this.toastService.info(
            'Utilisateur supprimé',
            `${response.user.email} a été supprimé (soft delete).`
          );
        },
        error: (err) => {
          this.usersError.set(err.message);
          this.actionLoading.set(null);
        }
      });
    } else if (action.type === 'hardDelete') {
      this.adminService.hardDeleteUser(action.user.id).subscribe({
        next: () => {
          this.users.update(users => users.filter(u => u.id !== action.user.id));
          this.confirmAction.set(null);
          this.actionLoading.set(null);
          this.toastService.error(
            'Utilisateur supprimé définitivement',
            `${action.user.email} a été supprimé définitivement.`
          );
        },
        error: (err) => {
          this.usersError.set(err.message);
          this.actionLoading.set(null);
        }
      });
    }
  }

  cancelConfirmAction(): void {
    this.confirmAction.set(null);
  }

  // === IMPERSONATION ===

  canImpersonate(user: AdminUser): boolean {
    if (!this.isSuperAdmin()) return false;
    if (this.isCurrentUser(user.id)) return false;
    if (user.roles.includes('ROLE_ADMIN') || user.roles.includes('ROLE_SUPER_ADMIN')) return false;
    if (user.isSuspended || user.deletedAt) return false;
    return true;
  }

  startImpersonation(user: AdminUser): void {
    this.actionLoading.set(user.id);

    this.adminService.startImpersonation(user.id, { reason: this.impersonationReason() }).subscribe({
      next: (response) => {
        this.tokenService.startImpersonation(
          response.token,
          response.refreshToken,
          response.sessionId,
          response.targetUser,
          new Date(response.expiresAt)
        );
        this.actionLoading.set(null);
        this.toastService.warning(
          'Impersonation active',
          `Vous agissez en tant que ${response.targetUser.email}. Expire à ${new Date(response.expiresAt).toLocaleTimeString()}.`
        );
        // Reload page to reflect new user context
        window.location.reload();
      },
      error: (err) => {
        this.usersError.set(err.message);
        this.actionLoading.set(null);
      }
    });
  }

  // === DELETE REQUESTS ===

  loadDeleteRequests(page: number = 1): void {
    this.deleteRequestsLoading.set(true);

    this.adminService.getDeleteRequests(page).subscribe({
      next: (response) => {
        this.deleteRequests.set(response.requests);
        this.deleteRequestsPagination.set(response.pagination);
        this.deleteRequestsLoading.set(false);
      },
      error: () => {
        this.deleteRequestsLoading.set(false);
      }
    });
  }

  approveDeleteRequest(request: AccountActionRequest): void {
    this.adminService.approveDeleteRequest(request.id).subscribe({
      next: () => {
        this.deleteRequests.update(requests => requests.filter(r => r.id !== request.id));
        this.loadDashboard(); // Refresh stats
        this.toastService.success(
          'Demande approuvée',
          `La demande de suppression de ${request.user.email} a été approuvée.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message);
      }
    });
  }

  rejectDeleteRequest(request: AccountActionRequest): void {
    const message = prompt('Raison du rejet (optionnel):');
    this.adminService.rejectDeleteRequest(request.id, message || undefined).subscribe({
      next: () => {
        this.deleteRequests.update(requests => requests.filter(r => r.id !== request.id));
        this.loadDashboard(); // Refresh stats
        this.toastService.info(
          'Demande rejetée',
          `La demande de suppression de ${request.user.email} a été rejetée.`
        );
      },
      error: (err) => {
        this.usersError.set(err.message);
      }
    });
  }

  // === MODAL HELPERS ===

  updateSuspendReason(reason: string): void {
    const modal = this.suspendModal();
    if (modal) {
      this.suspendModal.set({ ...modal, reason });
    }
  }

  updateSuspendUntil(until: string): void {
    const modal = this.suspendModal();
    if (modal) {
      this.suspendModal.set({ ...modal, until });
    }
  }

  // === USER DETAILS ===

  viewUserDetails(user: AdminUser): void {
    this.selectedUser.set(user);
  }

  closeUserDetails(): void {
    this.selectedUser.set(null);
  }

  // === HELPERS ===

  getUserStatusClass(user: AdminUser): string {
    if (user.deletedAt) return 'status-deleted';
    if (user.isSuspended) return 'status-suspended';
    if (!user.isEmailVerified) return 'status-unverified';
    return 'status-active';
  }

  getUserStatusLabel(user: AdminUser): string {
    if (user.deletedAt) return 'Supprimé';
    if (user.isSuspended) return 'Suspendu';
    if (!user.isEmailVerified) return 'Non vérifié';
    return 'Actif';
  }

  formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  // === REPORTS ===

  loadReports(): void {
    this.reportsLoading.set(true);

    this.reportChatService.getAllReports().subscribe({
      next: (response) => {
        this.reports.set(response.threads);
        this.mercureService.unreadReportsCount.set(response.unreadCount);
        this.reportsLoading.set(false);
      },
      error: () => {
        this.reportsLoading.set(false);
      }
    });
  }

  openReport(report: ReportThread): void {
    this.selectedReport.set(report);
    this.loadReportMessages(report.id);
  }

  closeReport(): void {
    this.selectedReport.set(null);
    this.reportMessages.set([]);
    this.newReportMessage.set('');
  }

  loadReportMessages(threadId: number): void {
    this.reportMessagesLoading.set(true);

    this.reportChatService.getThread(threadId).subscribe({
      next: (response) => {
        this.reportMessages.set(response.messages);
        this.reportMessagesLoading.set(false);
      },
      error: () => {
        this.reportMessagesLoading.set(false);
      }
    });
  }

  sendReportReply(): void {
    const report = this.selectedReport();
    const message = this.newReportMessage().trim();

    if (!report || !message) return;

    this.sendingMessage.set(true);

    this.reportChatService.replyToThread(report.id, message).subscribe({
      next: (response) => {
        this.reportMessages.update(messages => [...messages, response.reply]);
        this.newReportMessage.set('');
        this.sendingMessage.set(false);
      },
      error: () => {
        this.sendingMessage.set(false);
      }
    });
  }

  updateReportStatus(status: string): void {
    const report = this.selectedReport();
    if (!report) return;

    this.reportChatService.updateThreadStatus(report.id, status).subscribe({
      next: (response) => {
        this.selectedReport.set(response.thread);
        this.reports.update(reports =>
          reports.map(r => r.id === report.id ? response.thread : r)
        );
        this.toastService.success(
          'Statut mis à jour',
          `Le rapport a été marqué comme ${status}.`
        );
      }
    });
  }

  getReportStatusClass(status: string): string {
    switch (status) {
      case 'OPEN': return 'status-open';
      case 'IN_PROGRESS': return 'status-progress';
      case 'RESOLVED': return 'status-resolved';
      case 'CLOSED': return 'status-closed';
      default: return '';
    }
  }

  getReportStatusLabel(status: string): string {
    switch (status) {
      case 'OPEN': return 'Ouvert';
      case 'IN_PROGRESS': return 'En cours';
      case 'RESOLVED': return 'Résolu';
      case 'CLOSED': return 'Fermé';
      default: return status;
    }
  }

  // === AUDIT LOGS ===

  loadAuditLogs(page: number = 1): void {
    this.auditLogsLoading.set(true);

    this.adminService.getAuditLogs({
      page,
      limit: 20,
      action: this.auditActionFilter() || undefined,
      search: this.auditSearchQuery() || undefined
    }).subscribe({
      next: (response) => {
        this.auditLogs.set(response.logs);
        this.auditLogsPagination.set(response.pagination);
        this.auditLogsLoading.set(false);
      },
      error: () => {
        this.auditLogsLoading.set(false);
      }
    });
  }

  loadAuditActions(): void {
    this.adminService.getAuditActions().subscribe({
      next: (response) => {
        this.auditActions.set(response.actions);
      }
    });
  }

  onAuditFilterChange(): void {
    this.loadAuditLogs(1);
  }

  onAuditSearch(): void {
    this.loadAuditLogs(1);
  }

  onAuditPageChange(page: number): void {
    this.loadAuditLogs(page);
  }

  getAuditActionLabel(action: string): string {
    const labels: Record<string, string> = {
      'LOGIN': 'Connexion',
      'LOGOUT': 'Déconnexion',
      'LOGIN_FAILED': 'Échec connexion',
      'PASSWORD_CHANGE': 'Changement MDP',
      'PASSWORD_RESET_REQUEST': 'Demande reset MDP',
      'PASSWORD_RESET_COMPLETE': 'Reset MDP terminé',
      'DELETE_REQUEST': 'Demande suppression',
      'DELETE_REQUEST_APPROVED': 'Suppression approuvée',
      'DELETE_REQUEST_REJECTED': 'Suppression refusée',
      'SOFT_DELETE': 'Suppression (soft)',
      'RESTORE': 'Restauration',
      'HARD_DELETE': 'Suppression définitive',
      'ROLES_UPDATED': 'Rôles modifiés',
      'SUSPENDED': 'Suspendu',
      'UNSUSPENDED': 'Réactivé',
      'IMPERSONATION_START': 'Impersonation démarrée',
      'IMPERSONATION_STOP': 'Impersonation terminée',
      'EMAIL_VERIFIED': 'Email vérifié',
      'EMAIL_VERIFICATION_SENT': 'Vérification envoyée',
      'DATA_EXPORTED': 'Données exportées',
      'PROFILE_UPDATED': 'Profil mis à jour'
    };
    return labels[action] || action;
  }

  getAuditActionClass(action: string): string {
    if (action.includes('LOGIN') || action === 'LOGOUT') return 'action-auth';
    if (action.includes('PASSWORD')) return 'action-password';
    if (action.includes('DELETE') || action.includes('RESTORE')) return 'action-delete';
    if (action.includes('ROLE') || action.includes('SUSPEND')) return 'action-admin';
    if (action.includes('IMPERSONATION')) return 'action-impersonate';
    return 'action-default';
  }

  // === AUDIT LOG DETAIL MODAL ===

  openAuditDetail(log: AuditLogResponse): void {
    this.selectedAuditLog.set(log);
  }

  closeAuditDetail(): void {
    this.selectedAuditLog.set(null);
  }

  getMetadataEntries(metadata: Record<string, unknown> | null): Array<{ key: string; label: string; value: string }> {
    if (!metadata) return [];

    const labelMap: Record<string, string> = {
      'session_id': 'ID de session',
      'reason': 'Raison',
      'until': 'Jusqu\'au',
      'old_roles': 'Anciens rôles',
      'new_roles': 'Nouveaux rôles',
      'email': 'Email',
      'deleted_user_id': 'ID utilisateur supprimé',
      'deleted_user_email': 'Email supprimé',
      'changed_fields': 'Champs modifiés',
      'duration': 'Durée',
      'actions_count': 'Nombre d\'actions',
      'pages_visited': 'Pages visitées'
    };

    return Object.entries(metadata).map(([key, value]) => ({
      key,
      label: labelMap[key] || key.replace(/_/g, ' '),
      value: this.formatMetadataValue(key, value)
    }));
  }

  private formatMetadataValue(key: string, value: unknown): string {
    if (value === null || value === undefined) return '-';

    if (key === 'until' && typeof value === 'string') {
      return this.formatDate(value);
    }

    if (Array.isArray(value)) {
      return value.join(', ');
    }

    if (typeof value === 'object') {
      return JSON.stringify(value, null, 2);
    }

    return String(value);
  }

  getBrowserFromUserAgent(userAgent: string | null): string {
    if (!userAgent) return 'Inconnu';

    if (userAgent.includes('Firefox')) return 'Firefox';
    if (userAgent.includes('Edg')) return 'Edge';
    if (userAgent.includes('Chrome')) return 'Chrome';
    if (userAgent.includes('Safari')) return 'Safari';
    if (userAgent.includes('Opera') || userAgent.includes('OPR')) return 'Opera';
    return 'Autre';
  }

  getOSFromUserAgent(userAgent: string | null): string {
    if (!userAgent) return 'Inconnu';

    if (userAgent.includes('Windows')) return 'Windows';
    if (userAgent.includes('Mac OS')) return 'macOS';
    if (userAgent.includes('Linux')) return 'Linux';
    if (userAgent.includes('Android')) return 'Android';
    if (userAgent.includes('iOS') || userAgent.includes('iPhone')) return 'iOS';
    return 'Autre';
  }
}
