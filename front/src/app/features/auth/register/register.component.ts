import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { RegisterData } from '../../../core/models';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrl: './register.component.scss'
})
export class RegisterComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  registerData: RegisterData = {
    email: '',
    password: '',
    nomComplet: ''
  };

  confirmPassword = '';
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);
  loading = this.authService.loading;

  onSubmit(): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (!this.registerData.email || !this.registerData.password) {
      this.errorMessage.set('Veuillez remplir tous les champs obligatoires');
      return;
    }

    if (this.registerData.password !== this.confirmPassword) {
      this.errorMessage.set('Les mots de passe ne correspondent pas');
      return;
    }

    if (this.registerData.password.length < 6) {
      this.errorMessage.set('Le mot de passe doit contenir au moins 6 caractères');
      return;
    }

    this.authService.register(this.registerData).subscribe({
      next: () => {
        this.successMessage.set('Compte créé avec succès ! Redirection vers la connexion...');
        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 2000);
      },
      error: (error: Error) => {
        this.errorMessage.set(error.message);
      }
    });
  }
}
