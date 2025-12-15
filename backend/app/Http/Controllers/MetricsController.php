<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis as RedisStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function index()
    {
        $registry = new CollectorRegistry(new RedisStorage([
            'host' => config('database.redis.default.host'),
            'port' => config('database.redis.default.port'),
            'password' => config('database.redis.default.password'),
        ]));

        // Gauge: Active Users
        $activeUsers = $registry->getOrRegisterGauge(
            'teamchat',
            'active_users',
            'Number of active users'
        );
        $activeUsers->set(DB::table('users')->where('status', '!=', 'offline')->count());

        // Gauge: Total Messages (last 24h)
        $messagesGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'messages_24h',
            'Messages in last 24 hours'
        );
        $messagesGauge->set(DB::table('messages')
            ->where('created_at', '>=', now()->subDay())
            ->count()
        );

        // Gauge: Queue Size
        $queueGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'queue_size',
            'Number of pending queue jobs'
        );
        $queueGauge->set(Redis::llen('queues:default'));

        // Gauge: Active Connections
        $connectionsGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'websocket_connections',
            'Number of active WebSocket connections'
        );
        // Dies würde von Reverb kommen, placeholder
        $connectionsGauge->set(0);

        $renderer = new RenderTextFormat();
        return response($renderer->render($registry->getMetricFamilySamples()))
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
