import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  AdminUsersResponse,
  RolesResponse,
  UpdateRolesRequest,
  UpdateRolesResponse,
  ForgotPasswordRequest,
  ForgotPasswordResponse,
  VerifyTokenResponse,
  ResetPasswordRequest,
  ResetPasswordResponse
} from '../models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // === Gestion des utilisateurs ===

  /**
   * Récupérer la liste des utilisateurs avec recherche et pagination
   */
  getUsers(search: string = '', page: number = 1, limit: number = 10): Observable<AdminUsersResponse> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    if (search.trim()) {
      params = params.set('search', search.trim());
    }

    return this.http.get<AdminUsersResponse>(`${this.apiUrl}/admin/users`, { params });
  }

  /**
   * Modifier les rôles d'un utilisateur
   */
  updateUserRoles(userId: number, roles: string[]): Observable<UpdateRolesResponse> {
    const body: UpdateRolesRequest = { roles };
    return this.http.put<UpdateRolesResponse>(`${this.apiUrl}/admin/users/${userId}/roles`, body);
  }

  /**
   * Récupérer les rôles disponibles
   */
  getAvailableRoles(): Observable<RolesResponse> {
    return this.http.get<RolesResponse>(`${this.apiUrl}/admin/roles`);
  }

  // === Reset Password ===

  /**
   * Demander un reset de mot de passe
   */
  forgotPassword(email: string): Observable<ForgotPasswordResponse> {
    const body: ForgotPasswordRequest = { email };
    return this.http.post<ForgotPasswordResponse>(`${this.apiUrl}/password/forgot`, body);
  }

  /**
   * Vérifier si un token est valide
   */
  verifyResetToken(token: string): Observable<VerifyTokenResponse> {
    return this.http.get<VerifyTokenResponse>(`${this.apiUrl}/password/verify/${token}`);
  }

  /**
   * Réinitialiser le mot de passe
   */
  resetPassword(token: string, password: string): Observable<ResetPasswordResponse> {
    const body: ResetPasswordRequest = { token, password };
    return this.http.post<ResetPasswordResponse>(`${this.apiUrl}/password/reset`, body);
  }
}
