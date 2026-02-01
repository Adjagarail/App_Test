import { Component, inject, signal, computed, HostListener, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive, Router } from '@angular/router';
import { AuthService, NotificationService, TokenService, AdminService, ReportChatService, MercureService } from '../../../core/services';
import { Notification } from '../../../core/models';
import { ReportThread, ReportMessage } from '../../../core/services/report-chat.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './navbar.component.html',
  styleUrl: './navbar.component.scss'
})
export class NavbarComponent implements OnInit, OnDestroy {
  private readonly authService = inject(AuthService);
  private readonly notificationService = inject(NotificationService);
  private readonly tokenService = inject(TokenService);
  private readonly adminService = inject(AdminService);
  private readonly reportChatService = inject(ReportChatService);
  private readonly mercureService = inject(MercureService);
  private readonly router = inject(Router);

  // Auth signals
  isAuthenticated = this.authService.isAuthenticated;
  isSuperAdmin = this.authService.isSuperAdmin;
  isAdmin = this.authService.isAdmin;
  isSemiAdmin = this.authService.isSemiAdmin;
  currentUser = this.authService.currentUser;
  userRoles = this.authService.userRoles;

  // Impersonation
  isImpersonating = this.tokenService.isImpersonating;
  impersonationState = computed(() => this.tokenService.getImpersonationState());

  // Notification signals
  notifications = this.notificationService.notifications;
  unreadCount = this.notificationService.unreadCount;

  // UI state
  dropdownOpen = signal(false);
  notificationsOpen = signal(false);
  mobileMenuOpen = signal(false);
  darkMode = signal(false);

  // Report Chat state (for regular users)
  reportModalOpen = signal(false);
  reportChatOpen = signal(false);
  myReports = signal<ReportThread[]>([]);
  selectedReport = signal<ReportThread | null>(null);
  reportMessages = signal<ReportMessage[]>([]);
  newReportSubject = signal('');
  newReportMessage = signal('');
  chatMessage = signal('');
  reportLoading = signal(false);

  // Use MercureService signal for real-time unread count
  myReportsUnreadCount = this.mercureService.userUnreadReportsCount;

  // Computed
  userInitials = computed(() => {
    const user = this.currentUser();
    if (user?.nomComplet) {
      return user.nomComplet
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
    }
    if (user?.email) {
      return user.email[0].toUpperCase();
    }
    return '?';
  });

  displayName = computed(() => {
    const user = this.currentUser();
    return user?.nomComplet || user?.email || '';
  });

  primaryRole = computed(() => {
    const roles = this.userRoles();
    if (roles.includes('ROLE_SUPER_ADMIN')) return 'Super Admin';
    if (roles.includes('ROLE_ADMIN')) return 'Admin';
    if (roles.includes('ROLE_SEMI_ADMIN')) return 'Semi-Admin';
    if (roles.includes('ROLE_MODERATOR')) return 'Moderator';
    if (roles.includes('ROLE_ANALYST')) return 'Analyst';
    return 'User';
  });

  roleClass = computed(() => {
    const roles = this.userRoles();
    if (roles.includes('ROLE_SUPER_ADMIN')) return 'super-admin';
    if (roles.includes('ROLE_ADMIN')) return 'admin';
    if (roles.includes('ROLE_SEMI_ADMIN')) return 'semi-admin';
    if (roles.includes('ROLE_MODERATOR')) return 'moderator';
    if (roles.includes('ROLE_ANALYST')) return 'analyst';
    return 'user';
  });

  canAccessDashboard = computed(() => {
    return this.isAdmin() || this.isSemiAdmin();
  });

  // Notifications are only for ROLE_ADMIN and ROLE_SUPER_ADMIN
  canViewNotifications = computed(() => {
    return this.isAdmin();
  });

  constructor() {
    // Load dark mode preference
    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
      this.darkMode.set(true);
      document.body.classList.add('dark-mode');
    }
  }

  ngOnInit(): void {
    if (this.isAuthenticated()) {
      // Load admin notifications
      if (this.canViewNotifications()) {
        this.loadNotifications();
      }

      // Setup Mercure subscriptions for ALL authenticated users
      this.setupMercureSubscriptions();

      // Load initial unread count from API
      this.loadInitialUnreadCount();
    }
  }

  ngOnDestroy(): void {
    // Cleanup Mercure subscriptions
    const user = this.currentUser();
    if (user?.id && !this.isAdmin()) {
      this.mercureService.unsubscribeFromMyReports(user.id);
    }
  }

  private setupMercureSubscriptions(): void {
    const user = this.currentUser();
    if (!user?.id) return;

    // For regular users (not admins), subscribe to report responses
    if (!this.isAdmin()) {
      this.mercureService.subscribeToMyReports(user.id, (data) => {
        // Show notification for new admin response
        this.notificationService.addLocalNotification({
          type: 'info',
          title: 'Nouveau message',
          message: data.admin?.nomComplet
            ? `${data.admin.nomComplet} a répondu à votre rapport`
            : 'Un administrateur a répondu à votre rapport'
        });

        // Refresh reports if modal is open
        if (this.reportModalOpen()) {
          this.loadMyReports();

          // If viewing the specific thread, also refresh messages
          const selected = this.selectedReport();
          if (selected && selected.id === data.threadId) {
            this.loadReportMessages(selected.id);
          }
        }
      });
    }
  }

  private loadInitialUnreadCount(): void {
    // For regular users, load their unread report count
    if (!this.isAdmin()) {
      this.reportChatService.getMyReports(1, 1).subscribe({
        next: (response) => {
          this.mercureService.setInitialUnreadCount(response.unreadCount, false);
        },
        error: () => {} // Silently fail
      });
    }
  }

  loadNotifications(): void {
    this.notificationService.loadNotifications().subscribe({
      error: () => {} // Silently fail
    });
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: Event): void {
    const target = event.target as HTMLElement;

    // Close dropdown if click outside
    if (!target.closest('.user-dropdown-container')) {
      this.dropdownOpen.set(false);
    }

    // Close notifications if click outside
    if (!target.closest('.notifications-container')) {
      this.notificationsOpen.set(false);
    }
  }

  toggleDropdown(): void {
    this.dropdownOpen.update(v => !v);
    this.notificationsOpen.set(false);
  }

  toggleNotifications(): void {
    this.notificationsOpen.update(v => !v);
    this.dropdownOpen.set(false);

    // Load notifications when opening
    if (this.notificationsOpen()) {
      this.loadNotifications();
    }
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen.update(v => !v);
  }

  toggleDarkMode(): void {
    this.darkMode.update(v => {
      const newValue = !v;
      localStorage.setItem('darkMode', String(newValue));
      if (newValue) {
        document.body.classList.add('dark-mode');
      } else {
        document.body.classList.remove('dark-mode');
      }
      return newValue;
    });
  }

  markNotificationAsRead(id: number): void {
    this.notificationService.markAsRead(id).subscribe();
  }

  markAllNotificationsAsRead(): void {
    this.notificationService.markAllAsRead().subscribe();
  }

  navigateToNotification(notification: Notification): void {
    this.markNotificationAsRead(notification.id);
    if (notification.link) {
      this.router.navigateByUrl(notification.link);
    }
    this.notificationsOpen.set(false);
  }

  logout(): void {
    this.dropdownOpen.set(false);
    // Cleanup Mercure before logout
    this.mercureService.closeAll();
    this.authService.logout();
  }

  navigateTo(path: string): void {
    this.dropdownOpen.set(false);
    this.mobileMenuOpen.set(false);
    this.router.navigate([path]);
  }

  // Impersonation
  stopImpersonation(): void {
    this.adminService.stopImpersonation().subscribe({
      next: () => {
        this.tokenService.stopImpersonation();
        this.notificationService.addLocalNotification({
          type: 'info',
          title: 'Impersonation terminée',
          message: 'Vous êtes de retour à votre compte admin.'
        });
        window.location.reload();
      },
      error: () => {
        // Even if API fails, restore original tokens
        this.tokenService.stopImpersonation();
        window.location.reload();
      }
    });
  }

  // === Report Chat Methods (for regular users) ===

  openReportModal(): void {
    this.reportModalOpen.set(true);
    this.dropdownOpen.set(false);
    this.loadMyReports();
    // Reset unread count when opening the modal
    this.mercureService.resetUserUnreadCount();
  }

  closeReportModal(): void {
    this.reportModalOpen.set(false);
    this.selectedReport.set(null);
    this.reportMessages.set([]);
    this.reportChatOpen.set(false);
  }

  loadMyReports(): void {
    this.reportLoading.set(true);
    this.reportChatService.getMyReports().subscribe({
      next: (response) => {
        this.myReports.set(response.threads);
        // Update unread count from API response
        this.mercureService.setInitialUnreadCount(response.unreadCount, false);
        this.reportLoading.set(false);
      },
      error: () => {
        this.reportLoading.set(false);
      }
    });
  }

  openNewReportForm(): void {
    this.selectedReport.set(null);
    this.reportChatOpen.set(true);
    this.newReportSubject.set('');
    this.newReportMessage.set('');
  }

  submitNewReport(): void {
    const subject = this.newReportSubject().trim();
    const message = this.newReportMessage().trim();

    if (!message) return;

    this.reportLoading.set(true);
    this.reportChatService.createReport({ subject: subject || undefined, message }).subscribe({
      next: () => {
        this.notificationService.addLocalNotification({
          type: 'success',
          title: 'Rapport envoyé',
          message: 'Votre message a été envoyé aux administrateurs.'
        });
        this.newReportSubject.set('');
        this.newReportMessage.set('');
        this.reportChatOpen.set(false);
        this.loadMyReports();
      },
      error: () => {
        this.notificationService.addLocalNotification({
          type: 'error',
          title: 'Erreur',
          message: 'Impossible d\'envoyer le rapport.'
        });
        this.reportLoading.set(false);
      }
    });
  }

  openReportThread(report: ReportThread): void {
    this.selectedReport.set(report);
    this.reportChatOpen.set(true);
    this.loadReportMessages(report.id);
  }

  loadReportMessages(threadId: number): void {
    this.reportLoading.set(true);
    this.reportChatService.getThread(threadId).subscribe({
      next: (response) => {
        this.reportMessages.set(response.messages);
        this.reportLoading.set(false);
      },
      error: () => {
        this.reportLoading.set(false);
      }
    });
  }

  sendReportMessage(): void {
    const report = this.selectedReport();
    const message = this.chatMessage().trim();

    if (!report || !message) return;

    this.reportChatService.replyToThread(report.id, message).subscribe({
      next: (response) => {
        this.reportMessages.update(msgs => [...msgs, response.reply]);
        this.chatMessage.set('');
      },
      error: () => {
        this.notificationService.addLocalNotification({
          type: 'error',
          title: 'Erreur',
          message: 'Impossible d\'envoyer le message.'
        });
      }
    });
  }

  backToReportsList(): void {
    this.selectedReport.set(null);
    this.reportChatOpen.set(false);
    this.reportMessages.set([]);
    // Reload to update read status
    this.loadMyReports();
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      'OPEN': 'Ouvert',
      'IN_PROGRESS': 'En cours',
      'RESOLVED': 'Résolu',
      'CLOSED': 'Fermé'
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    return status.toLowerCase().replace('_', '-');
  }
}
