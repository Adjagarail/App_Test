export interface ApiResponse<T = unknown> {
  data?: T;
  message?: string;
  error?: string;
}

export interface ApiError {
  error: string;
  code?: string;
  details?: Record<string, string[]>;
}

export interface ProfileResponse {
  id: number;
  email: string;
  roles: string[];
  nomComplet: string | null;
  dateInscription: string;
  dateDerniereConnexion: string | null;
  isEmailVerified: boolean;
  emailVerifiedAt: string | null;
}

export interface DashboardResponse {
  message: string;
  user: {
    email: string;
    roles: string[];
    nomComplet: string;
  };
  stats: {
    totalUsers: number;
    todayLogins: number;
    activeSessions: number;
    pendingDeleteRequests: number;
    suspendedUsers: number;
    unverifiedEmails: number;
  };
}

// === Pagination ===

export interface PaginationInfo {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface PaginatedResponse<T> {
  items: T[];
  pagination: PaginationInfo;
}

// === Admin User Management (Features 1, 5, 9, 11) ===

export interface AdminUser {
  id: number;
  email: string;
  nomComplet: string | null;
  roles: string[];
  dateInscription: string;
  dateDerniereConnexion: string | null;
  isEmailVerified: boolean;
  isSuspended: boolean;
  suspendedUntil: string | null;
  suspensionReason: string | null;
  deletedAt: string | null;
}

export interface AdminUsersResponse {
  users: AdminUser[];
  pagination: PaginationInfo;
}

export interface AdminUserFilters {
  search?: string;
  role?: string;
  status?: 'active' | 'suspended' | 'deleted' | 'unverified';
  sortBy?: 'email' | 'dateInscription' | 'dateDerniereConnexion' | 'nomComplet';
  sortOrder?: 'asc' | 'desc';
  page?: number;
  limit?: number;
}

// === Roles (Feature 5) ===

export interface RoleDefinition {
  code: string;
  label: string;
  description: string;
}

export interface RolesResponse {
  roles: RoleDefinition[];
}

export interface UpdateRolesRequest {
  roles: string[];
}

export interface UpdateRolesResponse {
  message: string;
  user: AdminUser;
}

// === Account Action Requests (Feature 2) ===

export interface AccountActionRequest {
  id: number;
  user: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  type: string;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  message: string | null;
  createdAt: string;
  handledAt: string | null;
  handledBy: {
    id: number;
    email: string;
  } | null;
}

export interface AccountActionRequestsResponse {
  requests: AccountActionRequest[];
  pagination: PaginationInfo;
}

export interface HandleRequestResponse {
  message: string;
  request: AccountActionRequest;
}

// === Profile Update (Feature 3) ===

export interface UpdateProfileRequest {
  nomComplet: string;
}

export interface ChangePasswordRequest {
  currentPassword: string;
  newPassword: string;
  confirmNewPassword: string;
}

export interface ChangePasswordResponse {
  message: string;
  requiresRelogin?: boolean;
}

// === Password Reset Types ===

export interface ForgotPasswordRequest {
  email: string;
}

export interface ForgotPasswordResponse {
  message: string;
}

export interface VerifyTokenResponse {
  valid: boolean;
  email?: string;
  error?: string;
}

export interface ResetPasswordRequest {
  token: string;
  password: string;
}

export interface ResetPasswordResponse {
  message: string;
}

// === Notifications (Feature 8) ===

export interface NotificationResponse {
  id: number;
  type: string;
  payload: {
    title: string;
    message: string;
    link?: string;
    [key: string]: unknown;
  };
  isRead: boolean;
  createdAt: string;
  readAt: string | null;
}

export interface NotificationsResponse {
  notifications: NotificationResponse[];
  pagination: PaginationInfo;
  unreadCount: number;
}

export interface MarkNotificationReadResponse {
  message: string;
  notification: NotificationResponse;
}

// === Suspension (Feature 11) ===

export interface SuspendUserRequest {
  reason?: string;
  until?: string;
}

export interface SuspendUserResponse {
  message: string;
  user: AdminUser;
}

export interface UnsuspendUserResponse {
  message: string;
  user: AdminUser;
}

// === Impersonation (Feature 12) ===

export interface ImpersonationStartRequest {
  reason?: string;
}

export interface ImpersonationStartResponse {
  message: string;
  token: string;
  refreshToken: string;
  expiresAt: string;
  sessionId: string;
  targetUser: {
    id: number;
    email: string;
    nomComplet: string | null;
    roles: string[];
  };
}

export interface ImpersonationStopResponse {
  message: string;
}

export interface ImpersonationSession {
  id: string;
  targetUser: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  createdAt: string;
  expiresAt: string;
  isActive: boolean;
}

// === Email Verification (Feature 7) ===

export interface VerifyEmailResponse {
  message: string;
}

export interface ResendVerificationResponse {
  message: string;
}

// === Data Export (Feature 10) ===

export interface ExportDataResponse {
  user: ProfileResponse;
  notifications: NotificationResponse[];
  activityLog: AuditLogResponse[];
  exportedAt: string;
}

// === Audit Log (Feature 4) ===

export interface AuditLogResponse {
  id: number;
  actorUser: {
    id: number;
    email: string;
    nomComplet?: string | null;
  } | null;
  targetUser: {
    id: number;
    email: string;
    nomComplet?: string | null;
  } | null;
  action: string;
  metadata: Record<string, unknown> | null;
  ip: string | null;
  userAgent: string | null;
  createdAt: string;
}

export interface AuditLogsResponse {
  logs: AuditLogResponse[];
  pagination: PaginationInfo;
}

export interface AuditLogFilters {
  userId?: number;
  action?: string;
  search?: string;
  page?: number;
  limit?: number;
}

export interface AuditAction {
  code: string;
  label: string;
}

export interface AuditActionsResponse {
  actions: AuditAction[];
}

// === Soft Delete (Feature 6) ===

export interface SoftDeleteUserResponse {
  message: string;
  user: AdminUser;
}

export interface RestoreUserResponse {
  message: string;
  user: AdminUser;
}

export interface HardDeleteUserResponse {
  message: string;
}
