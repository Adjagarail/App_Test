import { Injectable, signal, computed } from '@angular/core';
import { JwtPayload } from '../models';

@Injectable({
  providedIn: 'root'
})
export class TokenService {
  private readonly TOKEN_KEY = 'access_token';
  private readonly REFRESH_TOKEN_KEY = 'refresh_token';

  private tokenSignal = signal<string | null>(this.getStoredToken());

  readonly token = this.tokenSignal.asReadonly();
  readonly isAuthenticated = computed(() => !!this.tokenSignal() && !this.isTokenExpired());

  getToken(): string | null {
    return this.tokenSignal();
  }

  getRefreshToken(): string | null {
    return localStorage.getItem(this.REFRESH_TOKEN_KEY);
  }

  setTokens(accessToken: string, refreshToken: string): void {
    localStorage.setItem(this.TOKEN_KEY, accessToken);
    localStorage.setItem(this.REFRESH_TOKEN_KEY, refreshToken);
    this.tokenSignal.set(accessToken);
  }

  clearTokens(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.REFRESH_TOKEN_KEY);
    this.tokenSignal.set(null);
  }

  decodeToken(): JwtPayload | null {
    const token = this.getToken();
    if (!token) return null;

    try {
      const payload = token.split('.')[1];
      const decoded = atob(payload);
      return JSON.parse(decoded) as JwtPayload;
    } catch {
      return null;
    }
  }

  isTokenExpired(): boolean {
    const payload = this.decodeToken();
    if (!payload) return true;

    const expirationDate = new Date(payload.exp * 1000);
    const now = new Date();

    // Considère le token comme expiré 60 secondes avant l'expiration réelle
    // pour permettre un refresh proactif
    return expirationDate.getTime() - now.getTime() < 60000;
  }

  getTokenExpirationDate(): Date | null {
    const payload = this.decodeToken();
    if (!payload) return null;
    return new Date(payload.exp * 1000);
  }

  getUserRoles(): string[] {
    const payload = this.decodeToken();
    return payload?.roles ?? [];
  }

  getUsername(): string | null {
    const payload = this.decodeToken();
    return payload?.username ?? null;
  }

  hasRole(role: string): boolean {
    return this.getUserRoles().includes(role);
  }

  hasAnyRole(roles: string[]): boolean {
    const userRoles = this.getUserRoles();
    return roles.some(role => userRoles.includes(role));
  }

  private getStoredToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }
}
