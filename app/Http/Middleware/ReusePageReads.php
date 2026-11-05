<?php

namespace App\Http\Middleware;

use App\Support\PageReadCache;
use ArrayObject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReusePageReads
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $request->attributes->set(PageReadCache::ATTRIBUTE, new ArrayObject());
        try {
            return $next($request);
        } finally {
            $request->attributes->remove(PageReadCache::ATTRIBUTE);
        }
    }
}
