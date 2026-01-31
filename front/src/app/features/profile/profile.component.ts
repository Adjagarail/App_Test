import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';
import { TokenService } from '../../core/services/token.service';
import { ProfileResponse } from '../../core/models';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './profile.component.html',
  styleUrl: './profile.component.scss'
})
export class ProfileComponent implements OnInit {
  private readonly apiService = inject(ApiService);
  private readonly authService = inject(AuthService);
  private readonly tokenService = inject(TokenService);

  profileData = signal<ProfileResponse | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  tokenExpiration = signal<Date | null>(null);
  isAdmin = this.authService.isAdmin;

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
      },
      error: (err) => {
        this.error.set('Erreur lors du refresh du token: ' + err.message);
      }
    });
  }

  formatDate(date: Date | null): string {
    if (!date) return 'N/A';
    return date.toLocaleString('fr-FR');
  }
}
