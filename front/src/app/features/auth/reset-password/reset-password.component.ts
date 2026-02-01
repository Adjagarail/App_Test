import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminService } from '../../../core/services/admin.service';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './reset-password.component.html',
  styleUrl: './reset-password.component.scss'
})
export class ResetPasswordComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly adminService = inject(AdminService);

  token = '';
  email = signal<string | null>(null);
  password = '';
  confirmPassword = '';

  loading = signal(false);
  verifying = signal(true);
  tokenValid = signal(false);
  success = signal(false);
  error = signal<string | null>(null);

  ngOnInit(): void {
    this.token = this.route.snapshot.params['token'] || '';

    if (!this.token) {
      this.error.set('Token manquant');
      this.verifying.set(false);
      return;
    }

    this.verifyToken();
  }

  verifyToken(): void {
    this.adminService.verifyResetToken(this.token).subscribe({
      next: (response) => {
        if (response.valid) {
          this.tokenValid.set(true);
          this.email.set(response.email || null);
        } else {
          this.error.set(response.error || 'Token invalide');
        }
        this.verifying.set(false);
      },
      error: () => {
        this.error.set('Token invalide ou expiré');
        this.verifying.set(false);
      }
    });
  }

  onSubmit(): void {
    this.error.set(null);

    if (this.password.length < 6) {
      this.error.set('Le mot de passe doit contenir au moins 6 caractères');
      return;
    }

    if (this.password !== this.confirmPassword) {
      this.error.set('Les mots de passe ne correspondent pas');
      return;
    }

    this.loading.set(true);

    this.adminService.resetPassword(this.token, this.password).subscribe({
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
