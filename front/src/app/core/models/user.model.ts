export interface User {
  id?: number;
  email: string;
  roles: string[];
  nomComplet?: string;
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
