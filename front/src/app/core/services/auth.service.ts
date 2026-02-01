import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, throwError, BehaviorSubject } from 'rxjs';
import { tap, catchError, finalize, switchMap } from 'rxjs/operators';
import { TokenService } from './token.service';
import { AuthResponse, LoginCredentials, RegisterData, User } from '../models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly tokenService = inject(TokenService);
  private readonly router = inject(Router);

  private readonly apiUrl = environment.apiUrl;
  private isRefreshing = false;
  private refreshTokenSubject = new BehaviorSubject<string | null>(null);

  private loadingSignal = signal(false);
  private currentUserSignal = signal<User | null>(null);

  readonly loading = this.loadingSignal.asReadonly();
  readonly currentUser = this.currentUserSignal.asReadonly();
  readonly isAuthenticated = this.tokenService.isAuthenticated;
  readonly isSuperAdmin = computed(() => this.tokenService.hasRole('ROLE_SUPER_ADMIN'));
  readonly isAdmin = computed(() => this.tokenService.hasRole('ROLE_ADMIN') || this.isSuperAdmin());
  readonly isSemiAdmin = computed(() => this.tokenService.hasRole('ROLE_SEMI_ADMIN'));
  readonly userRoles = computed(() => this.currentUserSignal()?.roles || []);

  constructor() {
    this.initializeUser();
  }

  private initializeUser(): void {
    if (this.tokenService.getToken()) {
      const id = this.tokenService.getUserId();
      const username = this.tokenService.getUsername();
      const roles = this.tokenService.getUserRoles();
      const nomComplet = this.tokenService.getNomComplet();
      if (username) {
        this.currentUserSignal.set({
          id: id || undefined,
          email: username,
          roles: roles,
          nomComplet: nomComplet || undefined
        });
      }
    }
  }

  login(credentials: LoginCredentials): Observable<AuthResponse> {
    this.loadingSignal.set(true);

    return this.http.post<AuthResponse>(`${this.apiUrl}/login_check`, credentials).pipe(
      tap(response => {
        this.tokenService.setTokens(response.token, response.refresh_token);
        this.initializeUser();
      }),
      catchError(error => this.handleError(error)),
      finalize(() => this.loadingSignal.set(false))
    );
  }

  register(data: RegisterData): Observable<User> {
    this.loadingSignal.set(true);

    return this.http.post<User>(`${this.apiUrl.replace('/api', '')}/register`, data).pipe(
      catchError(error => this.handleError(error)),
      finalize(() => this.loadingSignal.set(false))
    );
  }

  logout(): void {
    this.tokenService.clearTokens();
    this.currentUserSignal.set(null);
    this.router.navigate(['/login']);
  }

  refreshToken(): Observable<AuthResponse> {
    const refreshToken = this.tokenService.getRefreshToken();

    if (!refreshToken) {
      this.logout();
      return throwError(() => new Error('No refresh token available'));
    }

    if (this.isRefreshing) {
      return new Observable(observer => {
        this.refreshTokenSubject.subscribe(token => {
          if (token) {
            observer.next({ token, refresh_token: refreshToken });
            observer.complete();
          }
        });
      });
    }

    this.isRefreshing = true;
    this.refreshTokenSubject.next(null);

    return this.http.post<AuthResponse>(`${this.apiUrl}/token/refresh`, {
      refresh_token: refreshToken
    }).pipe(
      tap(response => {
        this.tokenService.setTokens(response.token, response.refresh_token);
        this.refreshTokenSubject.next(response.token);
        this.initializeUser();
      }),
      catchError(error => {
        this.logout();
        return throwError(() => error);
      }),
      finalize(() => {
        this.isRefreshing = false;
      })
    );
  }

  getRefreshTokenSubject(): BehaviorSubject<string | null> {
    return this.refreshTokenSubject;
  }

  isRefreshingToken(): boolean {
    return this.isRefreshing;
  }

  hasRole(role: string): boolean {
    return this.tokenService.hasRole(role);
  }

  hasAnyRole(roles: string[]): boolean {
    return this.tokenService.hasAnyRole(roles);
  }

  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'Une erreur est survenue';

    if (error.error instanceof ErrorEvent) {
      errorMessage = error.error.message;
    } else {
      switch (error.status) {
        case 401:
          errorMessage = 'Email ou mot de passe incorrect';
          break;
        case 403:
          errorMessage = 'Accès non autorisé';
          break;
        case 409:
          errorMessage = 'Cet email est déjà utilisé';
          break;
        case 500:
          errorMessage = 'Erreur serveur. Veuillez réessayer plus tard.';
          break;
        default:
          errorMessage = error.error?.message || error.message || errorMessage;
      }
    }

    return throwError(() => new Error(errorMessage));
  }
}
