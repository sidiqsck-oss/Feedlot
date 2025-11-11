'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useSession, signOut } from 'next-auth/react';
import { 
  Cattle, 
  Package, 
  Stethoscope, 
  DollarSign, 
  TrendingUp, 
  User, 
  LogOut,
  Menu,
  X
} from 'lucide-react';
import ThemeToggle from './ThemeToggle';

export default function Navigation() {
  const [isOpen, setIsOpen] = useState(false);
  const pathname = usePathname();
  const { data: session } = useSession();
  const userRole = session?.user?.role;

  const navigationItems = [
    { name: 'Dashboard', href: '/dashboard', icon: TrendingUp },
    { name: 'Cattle', href: '/cattle', icon: Cattle },
    { name: 'Feed', href: '/feed', icon: Package },
    { name: 'Health', href: '/health', icon: Stethoscope },
    { name: 'Sales', href: '/sales', icon: DollarSign },
  ];

  // Only show all items to managers and admins
  const filteredNavigationItems = userRole === 'OPERATOR' 
    ? navigationItems.filter(item => ['cattle', 'health'].some(path => item.href.includes(path)))
    : navigationItems;

  const closeMenu = () => setIsOpen(false);

  // Close menu when navigating
  useEffect(() => {
    setIsOpen(false);
  }, [pathname]);

  return (
    <nav className="bg-white shadow-md dark:bg-gray-800">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex items-center">
            <Link href="/dashboard" className="flex-shrink-0 flex items-center">
              <Cattle className="h-8 w-8 text-primary-600" />
              <span className="ml-2 text-xl font-bold text-gray-900 dark:text-white">Feedlot Manager</span>
            </Link>
            <div className="hidden md:ml-6 md:flex md:space-x-8">
              {filteredNavigationItems.map((item) => {
                const Icon = item.icon;
                return (
                  <Link
                    key={item.name}
                    href={item.href}
                    className={`${
                      pathname === item.href
                        ? 'border-primary-500 text-gray-900 dark:text-white dark:border-primary-500'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-200'
                    } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                  >
                    <Icon className="w-5 h-5 mr-2" />
                    {item.name}
                  </Link>
                );
              })}
            </div>
          </div>
          <div className="flex items-center">
            <div className="hidden md:ml-4 md:flex md:items-center space-x-4">
              <ThemeToggle />
              <div className="relative">
                <div className="flex items-center">
                  <User className="h-8 w-8 text-gray-400 dark:text-gray-300" />
                  <span className="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300 hidden md:block">
                    {session?.user?.name || 'User'}
                  </span>
                </div>
              </div>
              <button
                onClick={() => signOut({ callbackUrl: '/' })}
                className="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-200"
              >
                <LogOut className="h-5 w-5 mr-1" />
                Sign out
              </button>
            </div>
            <div className="-mr-2 flex items-center md:hidden">
              <ThemeToggle />
              <button
                onClick={() => setIsOpen(!isOpen)}
                className="ml-2 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:text-gray-300 dark:hover:text-gray-200 dark:hover:bg-gray-700"
              >
                <span className="sr-only">Open main menu</span>
                {isOpen ? (
                  <X className="block h-6 w-6" aria-hidden="true" />
                ) : (
                  <Menu className="block h-6 w-6" aria-hidden="true" />
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      {isOpen && (
        <div className="md:hidden">
          <div className="pt-2 pb-3 space-y-1">
            {filteredNavigationItems.map((item) => {
              const Icon = item.icon;
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={`${
                    pathname === item.href
                      ? 'bg-primary-50 border-primary-500 text-primary-700 dark:bg-gray-700 dark:border-primary-500 dark:text-white'
                      : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'
                  } block pl-3 pr-4 py-2 border-l-4 text-base font-medium flex items-center`}
                  onClick={closeMenu}
                >
                  <Icon className="w-5 h-5 mr-3" />
                  {item.name}
                </Link>
              );
            })}
          </div>
          <div className="pt-4 pb-3 border-t border-gray-200 dark:border-gray-700">
            <div className="flex items-center px-4">
              <div className="flex-shrink-0">
                <User className="h-10 w-10 text-gray-400 dark:text-gray-300" />
              </div>
              <div className="ml-3">
                <div className="text-base font-medium text-gray-800 dark:text-white">{session?.user?.name || 'User'}</div>
                <div className="text-sm font-medium text-gray-500 dark:text-gray-400">{userRole}</div>
              </div>
            </div>
            <div className="mt-3 space-y-1">
              <button
                onClick={() => signOut({ callbackUrl: '/' })}
                className="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100 w-full text-left dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700"
              >
                <LogOut className="h-5 w-5 mr-3 inline" />
                Sign out
              </button>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}