import { getServerSession } from 'next-auth';
import { authOptions } from './auth';

export async function hasRole(allowedRoles: string[], req?: any) {
  const session = await getServerSession(authOptions);
  
  if (!session || !session.user) {
    return false;
  }
  
  const userRole = session.user.role as string;
  return allowedRoles.includes(userRole);
}

export async function requireRole(allowedRoles: string[]) {
  const session = await getServerSession(authOptions);
  
  if (!session || !session.user) {
    throw new Error('Authentication required');
  }
  
  const userRole = session.user.role as string;
  if (!allowedRoles.includes(userRole)) {
    throw new Error('Access denied: Insufficient permissions');
  }
  
  return session.user;
}