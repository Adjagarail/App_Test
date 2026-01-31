export interface ApiResponse<T = unknown> {
  data?: T;
  message?: string;
  error?: string;
}

export interface ProfileResponse {
  email: string;
  roles: string[];
  nomComplet: string;
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
  };
}

// === Admin Types ===

export interface AdminUser {
  id: number;
  email: string;
  nomComplet: string | null;
  roles: string[];
}

export interface PaginationInfo {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface AdminUsersResponse {
  users: AdminUser[];
  pagination: PaginationInfo;
}

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
