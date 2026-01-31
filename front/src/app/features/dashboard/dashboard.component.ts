import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/services/api.service';
import { AdminService } from '../../core/services/admin.service';
import { AuthService } from '../../core/services/auth.service';
import {
  DashboardResponse,
  AdminUser,
  RoleDefinition,
  PaginationInfo
} from '../../core/models';

interface AccordionSection {
  id: string;
  title: string;
  isOpen: boolean;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent implements OnInit {
  private readonly apiService = inject(ApiService);
  private readonly adminService = inject(AdminService);
  private readonly authService = inject(AuthService);

  // Dashboard data
  dashboardData = signal<DashboardResponse | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  currentUser = this.authService.currentUser;
  isAdmin = this.authService.isAdmin;

  // Accordéon sections
  accordionSections = signal<AccordionSection[]>([
    { id: 'users', title: 'Gestion des utilisateurs', isOpen: false }
  ]);

  // Users management
  users = signal<AdminUser[]>([]);
  usersLoading = signal(false);
  usersError = signal<string | null>(null);
  searchQuery = signal('');
  pagination = signal<PaginationInfo>({ page: 1, limit: 10, total: 0, totalPages: 0 });

  // Roles management
  availableRoles = signal<RoleDefinition[]>([]);
  editingUserId = signal<number | null>(null);
  editingRoles = signal<string[]>([]);
  savingRoles = signal(false);

  ngOnInit(): void {
    this.loadDashboard();
    if (this.isAdmin()) {
      this.loadAvailableRoles();
    }
  }

  loadDashboard(): void {
    this.loading.set(true);
    this.error.set(null);

    this.apiService.getDashboard().subscribe({
      next: (data) => {
        this.dashboardData.set(data);
        this.loading.set(false);
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

  // === ACCORDÉON ===

  toggleSection(sectionId: string): void {
    this.accordionSections.update(sections =>
      sections.map(s => ({
        ...s,
        isOpen: s.id === sectionId ? !s.isOpen : s.isOpen
      }))
    );

    // Charger les utilisateurs si on ouvre la section
    const section = this.accordionSections().find(s => s.id === sectionId);
    if (section?.isOpen && sectionId === 'users' && this.users().length === 0) {
      this.loadUsers();
    }
  }

  isSectionOpen(sectionId: string): boolean {
    return this.accordionSections().find(s => s.id === sectionId)?.isOpen ?? false;
  }

  // === GESTION UTILISATEURS ===

  loadUsers(page: number = 1): void {
    this.usersLoading.set(true);
    this.usersError.set(null);

    this.adminService.getUsers(this.searchQuery(), page).subscribe({
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

  onPageChange(page: number): void {
    this.loadUsers(page);
  }

  loadAvailableRoles(): void {
    this.adminService.getAvailableRoles().subscribe({
      next: (response) => this.availableRoles.set(response.roles),
      error: () => {} // Silently fail
    });
  }

  // === GESTION DES RÔLES ===

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
        // Ne pas permettre de retirer ROLE_USER
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

    this.adminService.updateUserRoles(userId, this.editingRoles()).subscribe({
      next: (response) => {
        // Mettre à jour l'utilisateur dans la liste
        this.users.update(users =>
          users.map(u => u.id === userId ? response.user : u)
        );
        this.cancelEditRoles();
        this.savingRoles.set(false);
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
}
