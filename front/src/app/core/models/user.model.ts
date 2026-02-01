export interface User {
  id?: number;
  email: string;
  roles: string[];
  nomComplet?: string;
  dateInscription?: string;
  dateDerniereConnexion?: string;
  isEmailVerified?: boolean;
  emailVerifiedAt?: string;
  isSuspended?: boolean;
  suspendedUntil?: string;
  suspensionReason?: string;
  deletedAt?: string;
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  read: boolean;
  createdAt: string;
  readAt?: string;
  link?: string;
  payload?: Record<string, unknown>;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  email: string;
  password: string;
  nomComplet?: string;
}

// User roles
export type UserRole =
  | 'ROLE_USER'
  | 'ROLE_SEMI_ADMIN'
  | 'ROLE_MODERATOR'
  | 'ROLE_ANALYST'
  | 'ROLE_ADMIN'
  | 'ROLE_SUPER_ADMIN';
