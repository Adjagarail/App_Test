export interface ApiResponse<T = unknown> {
  data?: T;
  message?: string;
  error?: string;
}

export interface ProfileResponse {
  email: string;
  roles: string[];
}

export interface DashboardResponse {
  message: string;
  user: {
    email: string;
    roles: string[];
  };
  stats: {
    totalUsers: number;
    todayLogins: number;
  };
}
