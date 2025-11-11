'use client';

import { useSession } from 'next-auth/react';
import { ReactNode } from 'react';

interface RoleGuardProps {
  children: ReactNode;
  allowedRoles: string[];
  fallback?: ReactNode;
}

export default function RoleGuard({ 
  children, 
  allowedRoles, 
  fallback = <div>Access Denied</div> 
}: RoleGuardProps) {
  const { data: session, status } = useSession();

  if (status === 'loading') {
    return <div>Loading...</div>;
  }

  if (status === 'unauthenticated' || !session?.user) {
    return <div>Access Denied</div>;
  }

  const userRole = session.user.role as string;
  if (!allowedRoles.includes(userRole)) {
    return fallback;
  }

  return <>{children}</>;
}