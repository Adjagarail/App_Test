import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-unauthorized',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="unauthorized-container">
      <div class="unauthorized-card">
        <div class="icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1>Accès non autorisé</h1>
        <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
        <div class="actions">
          <a routerLink="/profile" class="btn btn-primary">Retour au profil</a>
          <a routerLink="/" class="btn btn-secondary">Accueil</a>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .unauthorized-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: calc(100vh - 80px);
      padding: 2rem;
    }

    .unauthorized-card {
      background: white;
      border-radius: 12px;
      padding: 3rem;
      text-align: center;
      max-width: 450px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .icon {
      width: 80px;
      height: 80px;
      background-color: #fef3c7;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;

      svg {
        width: 40px;
        height: 40px;
        color: #d97706;
      }
    }

    h1 {
      color: #1f2937;
      font-size: 1.5rem;
      margin: 0 0 1rem 0;
    }

    p {
      color: #6b7280;
      margin: 0 0 2rem 0;
    }

    .actions {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;

      &-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;

        &:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
      }

      &-secondary {
        background-color: #e5e7eb;
        color: #374151;

        &:hover {
          background-color: #d1d5db;
        }
      }
    }
  `]
})
export class UnauthorizedComponent {}
