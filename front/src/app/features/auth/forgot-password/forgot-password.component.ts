import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminService } from '../../../core/services/admin.service';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './forgot-password.component.html',
  styleUrl: './forgot-password.component.scss'
})
export class ForgotPasswordComponent {
  private readonly adminService = inject(AdminService);

  email = '';
  loading = signal(false);
  success = signal(false);
  error = signal<string | null>(null);

  onSubmit(): void {
    if (!this.email.trim()) {
      this.error.set('Veuillez entrer votre adresse email');
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.adminService.forgotPassword(this.email).subscribe({
      next: () => {
        this.success.set(true);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.message || 'Une erreur est survenue');
        this.loading.set(false);
      }
    });
  }
}
