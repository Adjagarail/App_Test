import { Component, inject, signal, OnInit, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';
import { TokenService } from '../../core/services/token.service';
import { UserService } from '../../core/services/user.service';
import { NotificationService } from '../../core/services/notification.service';
import { ProfileResponse } from '../../core/models';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './profile.component.html',
  styleUrl: './profile.component.scss'
})
export class ProfileComponent implements OnInit {
  private readonly apiService = inject(ApiService);
  private readonly authService = inject(AuthService);
  private readonly tokenService = inject(TokenService);
  private readonly userService = inject(UserService);
  private readonly notificationService = inject(NotificationService);

  profileData = signal<ProfileResponse | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);
  successMessage = signal<string | null>(null);

  tokenExpiration = signal<Date | null>(null);
  isAdmin = this.authService.isAdmin;
  isSuperAdmin = computed(() => this.tokenService.hasRole('ROLE_SUPER_ADMIN'));

  // Edit mode
  editMode = signal(false);
  editForm = signal({ nomComplet: '' });
  savingProfile = signal(false);

  // Password change
  passwordMode = signal(false);
  passwordForm = signal({
    currentPassword: '',
    newPassword: '',
    confirmNewPassword: ''
  });
  savingPassword = signal(false);

  // Account deletion
  deleteRequestMode = signal(false);
  deleteReason = signal('');
  requestingDelete = signal(false);
  hasPendingDeleteRequest = signal(false);

  // Data export
  exporting = signal(false);

  ngOnInit(): void {
    this.loadProfile();
    this.tokenExpiration.set(this.tokenService.getTokenExpirationDate());
  }

  loadProfile(): void {
    this.loading.set(true);
    this.error.set(null);

    this.apiService.getProfile().subscribe({
      next: (data) => {
        this.profileData.set(data);
        this.editForm.set({ nomComplet: data.nomComplet || '' });
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.message || 'Erreur lors du chargement du profil');
        this.loading.set(false);
      }
    });
  }

  refresh(): void {
    this.loadProfile();
  }

  refreshToken(): void {
    this.authService.refreshToken().subscribe({
      next: () => {
        this.tokenExpiration.set(this.tokenService.getTokenExpirationDate());
        this.showSuccess('Token rafraîchi avec succès');
      },
      error: (err) => {
        this.error.set('Erreur lors du refresh du token: ' + err.message);
      }
    });
  }

  // === Profile Editing ===

  startEdit(): void {
    this.editForm.set({ nomComplet: this.profileData()?.nomComplet || '' });
    this.editMode.set(true);
    this.clearMessages();
  }

  cancelEdit(): void {
    this.editMode.set(false);
  }

  saveProfile(): void {
    const form = this.editForm();
    if (!form.nomComplet.trim()) {
      this.error.set('Le nom complet est requis');
      return;
    }

    this.savingProfile.set(true);
    this.clearMessages();

    this.userService.updateProfile({ nomComplet: form.nomComplet.trim() }).subscribe({
      next: (response) => {
        this.profileData.set(response);
        this.editMode.set(false);
        this.savingProfile.set(false);
        this.showSuccess('Profil mis à jour avec succès');
        this.notificationService.addLocalNotification({
          type: 'success',
          title: 'Profil modifié',
          message: 'Vos informations ont été mises à jour.'
        });
      },
      error: (err) => {
        this.error.set(err.message || 'Erreur lors de la mise à jour du profil');
        this.savingProfile.set(false);
      }
    });
  }

  updateEditForm(field: 'nomComplet', value: string): void {
    this.editForm.update(form => ({ ...form, [field]: value }));
  }

  // === Password Change ===

  startPasswordChange(): void {
    this.passwordForm.set({
      currentPassword: '',
      newPassword: '',
      confirmNewPassword: ''
    });
    this.passwordMode.set(true);
    this.clearMessages();
  }

  cancelPasswordChange(): void {
    this.passwordMode.set(false);
  }

  savePassword(): void {
    const form = this.passwordForm();

    if (!form.currentPassword || !form.newPassword || !form.confirmNewPassword) {
      this.error.set('Tous les champs sont requis');
      return;
    }

    if (form.newPassword !== form.confirmNewPassword) {
      this.error.set('Les nouveaux mots de passe ne correspondent pas');
      return;
    }

    if (form.newPassword.length < 6) {
      this.error.set('Le mot de passe doit contenir au moins 6 caractères');
      return;
    }

    this.savingPassword.set(true);
    this.clearMessages();

    this.userService.changePassword({
      currentPassword: form.currentPassword,
      newPassword: form.newPassword,
      confirmNewPassword: form.confirmNewPassword
    }).subscribe({
      next: (response) => {
        this.passwordMode.set(false);
        this.savingPassword.set(false);
        if (response.requiresRelogin) {
          this.notificationService.addLocalNotification({
            type: 'warning',
            title: 'Reconnexion requise',
            message: 'Votre mot de passe a été changé. Veuillez vous reconnecter.'
          });
          setTimeout(() => {
            this.authService.logout();
          }, 2000);
        } else {
          this.showSuccess('Mot de passe modifié avec succès');
        }
      },
      error: (err) => {
        this.error.set(err.message || 'Erreur lors du changement de mot de passe');
        this.savingPassword.set(false);
      }
    });
  }

  updatePasswordForm(field: 'currentPassword' | 'newPassword' | 'confirmNewPassword', value: string): void {
    this.passwordForm.update(form => ({ ...form, [field]: value }));
  }

  // === Account Deletion Request ===

  startDeleteRequest(): void {
    this.deleteReason.set('');
    this.deleteRequestMode.set(true);
    this.clearMessages();
  }

  cancelDeleteRequest(): void {
    this.deleteRequestMode.set(false);
  }

  submitDeleteRequest(): void {
    this.requestingDelete.set(true);
    this.clearMessages();

    const reason = this.deleteReason().trim() || undefined;
    this.userService.requestAccountDeletion(reason).subscribe({
      next: () => {
        this.deleteRequestMode.set(false);
        this.requestingDelete.set(false);
        this.hasPendingDeleteRequest.set(true);
        this.showSuccess('Demande de suppression envoyée. Un administrateur traitera votre demande.');
        this.notificationService.addLocalNotification({
          type: 'info',
          title: 'Demande envoyée',
          message: 'Votre demande de suppression de compte a été envoyée aux administrateurs.'
        });
      },
      error: (err) => {
        if (err.error?.error?.code === 'REQUEST_ALREADY_EXISTS') {
          this.hasPendingDeleteRequest.set(true);
          this.error.set('Une demande de suppression est déjà en cours');
        } else {
          this.error.set(err.message || 'Erreur lors de l\'envoi de la demande');
        }
        this.deleteRequestMode.set(false);
        this.requestingDelete.set(false);
      }
    });
  }

  // === Data Export ===

  exportData(): void {
    this.exporting.set(true);
    this.clearMessages();

    this.userService.exportMyData().subscribe({
      next: (data) => {
        // Download as JSON file
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `mes-donnees-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        this.exporting.set(false);
        this.showSuccess('Données exportées avec succès');
        this.notificationService.addLocalNotification({
          type: 'success',
          title: 'Export réussi',
          message: 'Vos données ont été téléchargées.'
        });
      },
      error: (err) => {
        if (err.error?.error?.code === 'RATE_LIMITED') {
          this.error.set('Vous ne pouvez exporter vos données qu\'une fois par heure');
        } else {
          this.error.set(err.message || 'Erreur lors de l\'export des données');
        }
        this.exporting.set(false);
      }
    });
  }

  // === Helpers ===

  showSuccess(message: string): void {
    this.successMessage.set(message);
    setTimeout(() => this.successMessage.set(null), 5000);
  }

  clearMessages(): void {
    this.error.set(null);
    this.successMessage.set(null);
  }

  formatDate(date: Date | null): string {
    if (!date) return 'N/A';
    return date.toLocaleString('fr-FR');
  }

  formatDateString(dateString: string | null | undefined): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  getRoleBadgeClass(role: string): string {
    if (role === 'ROLE_SUPER_ADMIN') return 'super-admin';
    if (role === 'ROLE_ADMIN') return 'admin';
    if (role === 'ROLE_SEMI_ADMIN') return 'semi-admin';
    if (role === 'ROLE_MODERATOR') return 'moderator';
    if (role === 'ROLE_ANALYST') return 'analyst';
    return '';
  }
}
