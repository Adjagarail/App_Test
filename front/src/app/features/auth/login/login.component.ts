import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
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
