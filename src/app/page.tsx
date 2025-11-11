'use client';

import Link from 'next/link';
import { useSession } from 'next-auth/react';

export default function HomePage() {
  const { data: session } = useSession();

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50 flex flex-col items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-xl shadow-md p-8 space-y-6">
        <h1 className="text-3xl font-bold text-center text-gray-800">Feedlot Management System</h1>
        <p className="text-gray-600 text-center">
          Comprehensive cattle feedlot management with health tracking, feed management, and financial analytics
        </p>
        
        <div className="flex flex-col gap-4">
          {session ? (
            <>
              <p className="text-center">Welcome, {session.user?.name}</p>
              <Link 
                href="/dashboard" 
                className="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-200"
              >
                Go to Dashboard
              </Link>
            </>
          ) : (
            <>
              <Link 
                href="/auth/signin" 
                className="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-200"
              >
                Sign In
              </Link>
              <Link 
                href="/auth/signup" 
                className="w-full border border-primary-600 text-primary-600 hover:bg-primary-50 font-bold py-3 px-4 rounded-lg text-center transition duration-200"
              >
                Create Account
              </Link>
            </>
          )}
        </div>
      </div>
    </div>
  );
}