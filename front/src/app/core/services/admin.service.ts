import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  AdminUsersResponse,
  AdminUserFilters,
  RolesResponse,
  UpdateRolesRequest,
  UpdateRolesResponse,
  ForgotPasswordRequest,
  ForgotPasswordResponse,
  VerifyTokenResponse,
  ResetPasswordRequest,
  ResetPasswordResponse,
  AccountActionRequestsResponse,
  HandleRequestResponse,
  SuspendUserRequest,
  SuspendUserResponse,
  UnsuspendUserResponse,
  SoftDeleteUserResponse,
  RestoreUserResponse,
  HardDeleteUserResponse,
  AuditLogsResponse,
  AuditLogFilters,
  AuditActionsResponse,
  AdminUser,
  ImpersonationStartRequest,
  ImpersonationStartResponse,
  ImpersonationStopResponse,
} from '../models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // === User Management ===

  getUsers(filters: AdminUserFilters = {}): Observable<AdminUsersResponse> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.role) params = params.set('role', filters.role);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.sortBy) params = params.set('sortBy', filters.sortBy);
    if (filters.sortOrder) params = params.set('sortOrder', filters.sortOrder);
    if (filters.page) params = params.set('page', filters.page.toString());
    if (filters.limit) params = params.set('limit', filters.limit.toString());

    return this.http.get<AdminUsersResponse>(`${this.apiUrl}/admin/users`, { params });
  }

  getUser(id: number): Observable<AdminUser> {
    return this.http.get<AdminUser>(`${this.apiUrl}/admin/users/${id}`);
  }

  // === Roles ===

  getAvailableRoles(): Observable<RolesResponse> {
    return this.http.get<RolesResponse>(`${this.apiUrl}/admin/roles`);
  }

  updateUserRoles(userId: number, roles: string[]): Observable<UpdateRolesResponse> {
    const body: UpdateRolesRequest = { roles };
    return this.http.put<UpdateRolesResponse>(`${this.apiUrl}/admin/users/${userId}/roles`, body);
  }

  // === Account Action Requests (Delete Requests) ===

  getDeleteRequests(page = 1, limit = 20): Observable<AccountActionRequestsResponse> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString())
      .set('status', 'PENDING')
      .set('type', 'DELETE_ACCOUNT');

    return this.http.get<AccountActionRequestsResponse>(`${this.apiUrl}/admin/requests`, { params });
  }

  approveDeleteRequest(requestId: number): Observable<HandleRequestResponse> {
    return this.http.patch<HandleRequestResponse>(`${this.apiUrl}/admin/requests/${requestId}`, {
      status: 'APPROVED'
    });
  }

  rejectDeleteRequest(requestId: number, message?: string): Observable<HandleRequestResponse> {
    return this.http.patch<HandleRequestResponse>(`${this.apiUrl}/admin/requests/${requestId}`, {
      status: 'REJECTED',
      message
    });
  }

  // === Suspension ===

  suspendUser(userId: number, request: SuspendUserRequest = {}): Observable<SuspendUserResponse> {
    return this.http.patch<SuspendUserResponse>(`${this.apiUrl}/admin/users/${userId}/suspend`, request);
  }

  unsuspendUser(userId: number): Observable<UnsuspendUserResponse> {
    return this.http.patch<UnsuspendUserResponse>(`${this.apiUrl}/admin/users/${userId}/unsuspend`, {});
  }

  // === Soft Delete / Restore / Hard Delete ===

  softDeleteUser(userId: number): Observable<SoftDeleteUserResponse> {
    return this.http.delete<SoftDeleteUserResponse>(`${this.apiUrl}/admin/users/${userId}`);
  }

  restoreUser(userId: number): Observable<RestoreUserResponse> {
    return this.http.patch<RestoreUserResponse>(`${this.apiUrl}/admin/users/${userId}/restore`, {});
  }

  hardDeleteUser(userId: number): Observable<HardDeleteUserResponse> {
    return this.http.delete<HardDeleteUserResponse>(`${this.apiUrl}/admin/users/${userId}/hard`);
  }

  // === Audit Logs ===

  getAuditLogs(filters: AuditLogFilters = {}): Observable<AuditLogsResponse> {
    let params = new HttpParams()
      .set('page', (filters.page || 1).toString())
      .set('limit', (filters.limit || 20).toString());

    if (filters.userId) params = params.set('userId', filters.userId.toString());
    if (filters.action) params = params.set('action', filters.action);
    if (filters.search) params = params.set('search', filters.search);

    return this.http.get<AuditLogsResponse>(`${this.apiUrl}/admin/audit`, { params });
  }

  getAuditActions(): Observable<AuditActionsResponse> {
    return this.http.get<AuditActionsResponse>(`${this.apiUrl}/admin/audit/actions`);
  }

  // === Impersonation (SUPER_ADMIN only) ===

  startImpersonation(userId: number, request: ImpersonationStartRequest = {}): Observable<ImpersonationStartResponse> {
    return this.http.post<ImpersonationStartResponse>(`${this.apiUrl}/admin/users/${userId}/impersonate`, request);
  }

  stopImpersonation(): Observable<ImpersonationStopResponse> {
    return this.http.post<ImpersonationStopResponse>(`${this.apiUrl}/admin/impersonation/stop`, {});
  }

  // === Password Reset ===

  forgotPassword(email: string): Observable<ForgotPasswordResponse> {
    const body: ForgotPasswordRequest = { email };
    return this.http.post<ForgotPasswordResponse>(`${this.apiUrl}/password/forgot`, body);
  }

  verifyResetToken(token: string): Observable<VerifyTokenResponse> {
    return this.http.get<VerifyTokenResponse>(`${this.apiUrl}/password/verify/${token}`);
  }

  resetPassword(token: string, password: string): Observable<ResetPasswordResponse> {
    const body: ResetPasswordRequest = { token, password };
    return this.http.post<ResetPasswordResponse>(`${this.apiUrl}/password/reset`, body);
  }
}
