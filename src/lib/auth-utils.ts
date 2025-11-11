import { getServerSession } from 'next-auth';
import { authOptions } from './auth';

export async function getCurrentUser() {
  const session = await getServerSession(authOptions);
  return session?.user;
}

export async function requireAuth() {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error('Authentication required');
  }
  return user;
}

export async function requireRole(allowedRoles: string[]) {
  const user = await requireAuth();
  if (!user || !allowedRoles.includes(user.role as string)) {
    throw new Error('Access denied: Insufficient permissions');
  }
  return user;
}