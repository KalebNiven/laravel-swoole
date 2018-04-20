<?php

namespace SwooleTW\Http\Websocket;

use InvalidArgumentException;
use Illuminate\Support\Facades\App;
use SwooleTW\Http\Websocket\Rooms\RoomContract;

class Websocket
{
    const PUSH_ACTION = 'push';

    protected $isBroadcast = false;

    protected $sender;

    protected $to = [];

    protected $callbacks = [];

    /**
     * Room.
     *
     * @var SwooleTW\Http\Websocket\Rooms\RoomContract
     */
    protected $room;

    // https://gist.github.com/alexpchin/3f257d0bb813e2c8c476
    // https://github.com/socketio/socket.io/blob/master/docs/emit.md
    public function __construct(RoomContract $room)
    {
        $this->room = $room;
    }

    public function broadcast()
    {
        $this->isBroadcast = true;

        return $this;
    }

    /**
     * @param integer, string (fd or room)
     */
    public function to($value)
    {
        $this->toAll([$value]);

        return $this;
    }

    /**
     * @param array (fds or rooms)
     */
    public function toAll(array $values)
    {
        foreach ($values as $value) {
            if (! in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * @param string
     */
    public function join(string $room)
    {
        $this->room->add($this->sender, $room);

        return $this;
    }

    /**
     * @param array
     */
    public function joinAll(array $rooms)
    {
        $this->room->addAll($this->sender, $rooms);

        return $this;
    }

    /**
     * @param string
     */
    public function leave(string $room)
    {
        $this->room->delete($this->sender, $room);

        return $this;
    }

    /**
     * @param array
     */
    public function leaveAll(array $rooms = [])
    {
        $this->room->deleteAll($this->sender, $rooms);

        return $this;
    }

    public function emit(string $event, $data)
    {
        app('swoole.server')->task([
            'action' => static::PUSH_ACTION,
            'data' => [
                'sender' => $this->sender,
                'fds' => $this->getFds(),
                'broadcast' => $this->isBroadcast,
                'event' => $event,
                'message' => $data
            ]
        ]);

        $this->reset();
    }

    public function in(string $room)
    {
        $this->join($room);

        return $this;
    }

    public function on(string $event, $callback)
    {
        if (! is_string($callback) && ! is_callable($callback)) {
            throw new InvalidArgumentException(
                'Invalid websocket callback. Must be a string or callable.'
            );
        }

        $this->callbacks[$event] = $callback;

        return $this;
    }

    public function eventExists(string $event)
    {
        return array_key_exists($event, $this->callbacks);
    }

    public function call(string $event, $data = null)
    {
        return App::call($this->callbacks[$event], [
            'websocket' => $this,
            'data' => $data
        ]);
    }

    public function setSender(int $fd)
    {
        $this->sender = $fd;

        return $this;
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function getIsBroadcast()
    {
        return $this->isBroadcast;
    }

    public function getTo()
    {
        return $this->to;
    }

    protected function getFds()
    {
        $fds = array_filter($this->to, function ($value) {
            return is_integer($value);
        });
        $rooms = array_diff($this->to, $fds);

        foreach ($rooms as $room) {
            $fds = array_merge($fds, $this->room->getClients($room));
        }

        return array_values(array_unique($fds));
    }

    protected function reset()
    {
        $this->isBroadcast = false;
        $this->sender = null;
        $this->to = [];
    }
}
