/** @type {import('next').NextConfig} */
const nextConfig = {
  experimental: {
    // Tambahkan konfigurasi eksperimental jika diperlukan
  },
  webpack: (config, { isServer }) => {
    // Konfigurasi webpack tambahan jika diperlukan
    return config;
  },
};

module.exports = nextConfig;