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
  nomComplet: string;
}
