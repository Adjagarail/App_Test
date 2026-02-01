import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  ProfileResponse,
  UpdateProfileRequest,
  ChangePasswordRequest,
  ChangePasswordResponse,
  ExportDataResponse,
  VerifyEmailResponse,
  ResendVerificationResponse,
  ApiResponse,
} from '../models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // === Profile ===

  getProfile(): Observable<ProfileResponse> {
    return this.http.get<ProfileResponse>(`${this.apiUrl}/me`);
  }

  updateProfile(data: UpdateProfileRequest): Observable<ProfileResponse> {
    return this.http.patch<ProfileResponse>(`${this.apiUrl}/me`, data);
  }

  // === Password ===

  changePassword(data: ChangePasswordRequest): Observable<ChangePasswordResponse> {
    return this.http.patch<ChangePasswordResponse>(`${this.apiUrl}/me/password`, data);
  }

  // === Account Deletion Request ===

  requestAccountDeletion(reason?: string): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.apiUrl}/me/requests/delete-account`, { reason });
  }

  cancelDeletionRequest(): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.apiUrl}/me/requests/delete-account`);
  }

  // === Data Export (GDPR) ===

  exportMyData(): Observable<ExportDataResponse> {
    return this.http.get<ExportDataResponse>(`${this.apiUrl}/me/export`);
  }

  // === Email Verification ===

  verifyEmail(token: string): Observable<VerifyEmailResponse> {
    return this.http.get<VerifyEmailResponse>(`${this.apiUrl}/auth/verify-email/${token}`);
  }

  resendVerificationEmail(): Observable<ResendVerificationResponse> {
    return this.http.post<ResendVerificationResponse>(`${this.apiUrl}/auth/resend-verification`, {});
  }
}
