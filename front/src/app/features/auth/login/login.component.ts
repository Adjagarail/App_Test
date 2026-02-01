import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { MercureService } from '../../../core/services/mercure.service';
import { TokenService } from '../../../core/services/token.service';
import { LoginCredentials } from '../../../core/models';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})

export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly mercureService = inject(MercureService);
  private readonly tokenService = inject(TokenService);
  private readonly router = inject(Router);

  credentials: LoginCredentials = {
    email: '',
    password: ''
  };

  errorMessage = signal<string | null>(null);
  loading = this.authService.loading;

  onSubmit(): void {
    this.errorMessage.set(null);

    if (!this.credentials.email || !this.credentials.password) {
      this.errorMessage.set('Veuillez remplir tous les champs');
      return;
    }

    this.authService.login(this.credentials).subscribe({
      next: () => {
        const isAdmin = this.authService.isAdmin();
        const userId = this.tokenService.getUserId();

        // Initialize Mercure subscriptions for real-time updates
        if (userId) {
          this.mercureService.initializeForUser(userId, isAdmin);
        }

        if (isAdmin) {
          this.router.navigate(['/dashboard']);
        } else {
          this.router.navigate(['/profile']);
        }
      },
      error: (error: Error) => {
        this.errorMessage.set(error.message);
      }
    });
  }
}
