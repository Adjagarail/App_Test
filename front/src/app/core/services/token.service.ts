import { Injectable, signal, computed } from '@angular/core';
import { JwtPayload, ImpersonationState } from '../models';

@Injectable({
  providedIn: 'root'
})
export class TokenService {
  private readonly TOKEN_KEY = 'access_token';
  private readonly REFRESH_TOKEN_KEY = 'refresh_token';
  private readonly ORIGINAL_TOKEN_KEY = 'original_access_token';
  private readonly ORIGINAL_REFRESH_TOKEN_KEY = 'original_refresh_token';
  private readonly IMPERSONATION_STATE_KEY = 'impersonation_state';

  private tokenSignal = signal<string | null>(this.getStoredToken());

  readonly token = this.tokenSignal.asReadonly();
  readonly isAuthenticated = computed(() => !!this.tokenSignal() && !this.isTokenExpired());
  readonly isImpersonating = computed(() => this.getImpersonationState().isImpersonating);

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
    this.clearImpersonation();
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

  getNomComplet(): string | null {
    const payload = this.decodeToken();
    return payload?.nomComplet ?? null;
  }

  getUserId(): number | null {
    const payload = this.decodeToken();
    return payload?.id ?? null;
  }

  isEmailVerified(): boolean {
    const payload = this.decodeToken();
    return payload?.isEmailVerified ?? false;
  }

  hasRole(role: string): boolean {
    return this.getUserRoles().includes(role);
  }

  hasAnyRole(roles: string[]): boolean {
    const userRoles = this.getUserRoles();
    return roles.some(role => userRoles.includes(role));
  }

  // === Impersonation ===

  startImpersonation(
    newToken: string,
    newRefreshToken: string,
    sessionId: string,
    targetUser: { id: number; email: string; nomComplet: string | null },
    expiresAt: Date
  ): void {
    // Store original tokens
    const currentToken = this.getToken();
    const currentRefresh = this.getRefreshToken();

    if (currentToken) {
      localStorage.setItem(this.ORIGINAL_TOKEN_KEY, currentToken);
    }
    if (currentRefresh) {
      localStorage.setItem(this.ORIGINAL_REFRESH_TOKEN_KEY, currentRefresh);
    }

    // Set impersonation state
    const state: ImpersonationState = {
      isImpersonating: true,
      sessionId,
      targetUser,
      expiresAt
    };
    localStorage.setItem(this.IMPERSONATION_STATE_KEY, JSON.stringify(state));

    // Set new tokens
    this.setTokens(newToken, newRefreshToken);
  }

  stopImpersonation(): boolean {
    const originalToken = localStorage.getItem(this.ORIGINAL_TOKEN_KEY);
    const originalRefresh = localStorage.getItem(this.ORIGINAL_REFRESH_TOKEN_KEY);

    if (originalToken && originalRefresh) {
      this.setTokens(originalToken, originalRefresh);
      this.clearImpersonation();
      return true;
    }

    return false;
  }

  getImpersonationState(): ImpersonationState {
    const stored = localStorage.getItem(this.IMPERSONATION_STATE_KEY);
    if (stored) {
      try {
        const state = JSON.parse(stored) as ImpersonationState;
        if (state.expiresAt) {
          state.expiresAt = new Date(state.expiresAt);
        }
        return state;
      } catch {
        return { isImpersonating: false };
      }
    }
    return { isImpersonating: false };
  }

  isImpersonationExpired(): boolean {
    const state = this.getImpersonationState();
    if (!state.isImpersonating || !state.expiresAt) {
      return false;
    }
    return new Date() > state.expiresAt;
  }

  private clearImpersonation(): void {
    localStorage.removeItem(this.ORIGINAL_TOKEN_KEY);
    localStorage.removeItem(this.ORIGINAL_REFRESH_TOKEN_KEY);
    localStorage.removeItem(this.IMPERSONATION_STATE_KEY);
  }

  private getStoredToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }
}
