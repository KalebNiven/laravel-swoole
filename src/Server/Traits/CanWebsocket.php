<?php

namespace SwooleTW\Http\Server\Traits;

use SwooleTW\Http\Server\Request;
use SwooleTW\Http\Server\Websocket\Formatter\FormatterContract;

trait CanWebsocket
{
    /**
     * @var boolean
     */
    protected $isWebsocket = false;

    /**
     * @var SwooleTW\Http\Server\Websocket\Formatter\FormatterContract
     */
    protected $formatter;

    /**
     * Websocket server events.
     *
     * @var array
     */
    protected $wsEvents = ['open', 'message', 'close'];

    /**
     * "onOpen" listener.
     *
     * @param \Swoole\Websocket\Server $server
     * @param \Swoole\Http\Request $swooleRequest
     */
    public function onOpen($server, $swooleRequest)
    {
        $this->container['events']->fire('swoole.onOpen', $swooleRequest);
    }

    /**
     * "onMessage" listener.
     *
     * @param \Swoole\Websocket\Server $server
     * @param \Swoole\Websocket\Frame $frame
     */
    public function onMessage($server, $frame)
    {
        $this->container['swoole.websocket']->setSender($frame->fd);
        $this->container['events']->fire('swoole.onMessage', $frame);
    }

    /**
     * "onClose" listener.
     *
     * @param \Swoole\Websocket\Server $server
     * @param int $fd
     */
    public function onClose($server, $fd)
    {
        $info = $server->connection_info($fd);
        if (array_key_exists('websocket_status', $info) && $info['websocket_status']) {
            $this->container['events']->fire('swoole.onClose', $fd);
            $this->container['swoole.websocket']->setSender($fd)->leaveAll();
        }
    }

    /**
     * Push websocket message to clients.
     *
     * @param \Swoole\Websocket\Server $server
     * @param mixed $data
     */
    public function pushMessage($server, array $data)
    {
        $opcode = $data['opcode'] ?? 1;
        $sender = $data['sender'] ?? 0;
        $fds = $data['fds'] ?? [];
        $broadcast = $data['broadcast'] ?? false;
        $message = $this->formatter->output($data['message']);

        foreach ($fds as $fd) {
            if ($broadcast && $sender === (integer) $fd) {
                continue;
            }
            $server->push($fd, $message, $opcode);
        }
    }

    /**
     * Set message formatter for websocket.
     *
     * @param \SwooleTW\Http\Server\Websocket\Formatter\FormatterContract $formatter
     */
    public function setFormatter(FormatterContract $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Get message formatter for websocket.
     */
    public function getFormatter()
    {
        return $this->formatter;
    }
}
