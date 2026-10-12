/** @type {import('next').NextConfig} */
const nextConfig = {
  async rewrites() {
    const phpOrigin = process.env.PHP_ORIGIN || 'http://127.0.0.1';
    return [
      {
        source: '/movie-streaming/:path*',
        destination: `${phpOrigin}/movie-streaming/:path*`,
      },
    ];
  },
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'image.tmdb.org',
      },
      {
        protocol: 'https',
        hostname: 'images.unsplash.com',
      },
    ],
  },
};

export default nextConfig;
