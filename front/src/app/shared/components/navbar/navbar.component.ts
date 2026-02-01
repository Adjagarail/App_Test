import { Component, inject, signal, computed, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, Router } from '@angular/router';
import { AuthService, NotificationService } from '../../../core/services';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './navbar.component.html',
  styleUrl: './navbar.component.scss'
})
export class NavbarComponent {
  private readonly authService = inject(AuthService);
  private readonly notificationService = inject(NotificationService);
  private readonly router = inject(Router);

  // Auth signals
  isAuthenticated = this.authService.isAuthenticated;
  isAdmin = this.authService.isAdmin;
  isSemiAdmin = this.authService.isSemiAdmin;
  currentUser = this.authService.currentUser;
  userRoles = this.authService.userRoles;

  // Notification signals
  notifications = this.notificationService.notifications;
  unreadCount = this.notificationService.unreadCount;

  // UI state
  dropdownOpen = signal(false);
  notificationsOpen = signal(false);
  mobileMenuOpen = signal(false);
  darkMode = signal(false);

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
    if (roles.includes('ROLE_ADMIN')) return 'Admin';
    if (roles.includes('ROLE_SEMI_ADMIN')) return 'Semi-Admin';
    if (roles.includes('ROLE_MODERATOR')) return 'Moderator';
    if (roles.includes('ROLE_ANALYST')) return 'Analyst';
    return 'User';
  });

  roleClass = computed(() => {
    const roles = this.userRoles();
    if (roles.includes('ROLE_ADMIN')) return 'admin';
    if (roles.includes('ROLE_SEMI_ADMIN')) return 'semi-admin';
    if (roles.includes('ROLE_MODERATOR')) return 'moderator';
    if (roles.includes('ROLE_ANALYST')) return 'analyst';
    return 'user';
  });

  canAccessDashboard = computed(() => {
    return this.isAdmin() || this.isSemiAdmin();
  });

  constructor() {
    // Load dark mode preference
    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
      this.darkMode.set(true);
      document.body.classList.add('dark-mode');
    }
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

  markNotificationAsRead(id: string): void {
    this.notificationService.markAsRead(id);
  }

  markAllNotificationsAsRead(): void {
    this.notificationService.markAllAsRead();
  }

  navigateToNotification(notification: { id: string; link?: string }): void {
    this.markNotificationAsRead(notification.id);
    if (notification.link) {
      this.router.navigateByUrl(notification.link);
    }
    this.notificationsOpen.set(false);
  }

  logout(): void {
    this.dropdownOpen.set(false);
    this.authService.logout();
  }

  navigateTo(path: string): void {
    this.dropdownOpen.set(false);
    this.mobileMenuOpen.set(false);
    this.router.navigate([path]);
  }
}
