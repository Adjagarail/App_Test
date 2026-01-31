import { inject } from '@angular/core';
import { Router, CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { TokenService } from '../services/token.service';

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

  if (tokenService.hasAnyRole(requiredRoles)) {
    return true;
  }

  router.navigate(['/unauthorized']);
  return false;
};
