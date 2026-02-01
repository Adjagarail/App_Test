import { inject } from '@angular/core';
import { Router, CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { TokenService } from '../services/token.service';

/**
 * Role hierarchy - mirrors backend security.yaml
 * ROLE_SUPER_ADMIN inherits all roles
 * ROLE_ADMIN inherits ROLE_SEMI_ADMIN, ROLE_MODERATOR, ROLE_ANALYST
 */
const ROLE_HIERARCHY: Record<string, string[]> = {
  'ROLE_SUPER_ADMIN': ['ROLE_ADMIN', 'ROLE_SEMI_ADMIN', 'ROLE_MODERATOR', 'ROLE_ANALYST', 'ROLE_USER'],
  'ROLE_ADMIN': ['ROLE_SEMI_ADMIN', 'ROLE_MODERATOR', 'ROLE_ANALYST', 'ROLE_USER'],
  'ROLE_SEMI_ADMIN': ['ROLE_USER'],
  'ROLE_MODERATOR': ['ROLE_USER'],
  'ROLE_ANALYST': ['ROLE_USER'],
  'ROLE_USER': []
};

/**
 * Get all effective roles for a user based on role hierarchy
 */
function getEffectiveRoles(userRoles: string[]): string[] {
  const effectiveRoles = new Set<string>(userRoles);

  userRoles.forEach(role => {
    const inheritedRoles = ROLE_HIERARCHY[role] || [];
    inheritedRoles.forEach(inheritedRole => effectiveRoles.add(inheritedRole));
  });

  return Array.from(effectiveRoles);
}

export const roleGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const tokenService = inject(TokenService);
  const router = inject(Router);

  const requiredRoles = route.data['roles'] as string[] | undefined;

  if (!requiredRoles || requiredRoles.length === 0) {
    return true;
  }

  if (!tokenService.isAuthenticated()) {
    router.navigate(['/login']);
    return false;
  }

  // Get user's effective roles including inherited ones
  const userRoles = tokenService.getUserRoles();
  const effectiveRoles = getEffectiveRoles(userRoles);

  // Check if user has any of the required roles
  const hasAccess = requiredRoles.some(role => effectiveRoles.includes(role));

  if (hasAccess) {
    return true;
  }

  router.navigate(['/unauthorized']);
  return false;
};
