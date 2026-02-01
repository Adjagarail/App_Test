export interface AuthResponse {
  token: string;
  refresh_token: string;
}

export interface RefreshTokenRequest {
  refresh_token: string;
}

export interface JwtPayload {
  iat: number;
  exp: number;
  roles: string[];
  username: string;
  id: number;
  nomComplet: string;
  isEmailVerified?: boolean;
  // Impersonation fields
  isImpersonating?: boolean;
  impersonationSessionId?: string;
  impersonatorId?: number;
}

export interface ImpersonationState {
  isImpersonating: boolean;
  sessionId?: string;
  impersonatorId?: number;
  originalToken?: string;
  originalRefreshToken?: string;
  targetUser?: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  expiresAt?: Date;
}
