<?php
/**
 * Phergie plugin for Pull excuses from bastard operator from hell (phergie-irc-plugin-react-bofh)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-bofh for the canonical source repository
 * @copyright Copyright (c) 2015 Joe Ferguson (http://www.joeferguson.me)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\BOFH
 */

namespace Phergie\Irc\Plugin\React\BOFH;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEventInterface as Event;
use Phergie\Plugin\Http\Request;
use Phergie\Irc\Plugin\React\Url\Url;
use GuzzleHttp\Message\Response;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package phergie/phergie-irc-plugin-react-bofh
 */
class Plugin extends AbstractPlugin
{
    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     *
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {

    }

    /**
     *  Return subscribed events
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'command.bofh' => 'handleBofhCommand',
            'command.bofh.help' => 'handleBofhHelpCommand',
        ];
    }

    /**
     * Process the command
     * @param Event $event
     * @param Queue $queue
     */
    public function handleBofhCommand(Event $event, Queue $queue)
    {
        $this->getLogger()->info('[BOFH] received a new command');

        $this->fetchExcuse($event, $queue);
    }

    /**
     * Process the help command
     * @param Event $event
     * @param Queue $queue
     */
    public function handleBofhHelpCommand(Event $event, Queue $queue)
    {
        $messages = [
            'Usage: bofh'
        ];
        foreach ($messages as $message) {
            $queue->ircPrivmsg($event->getSource(), $message);
        }
    }

    /**
     * Fetch the URL and parse an excuse
     * @param $event
     * @param $queue
     */
    public function fetchExcuse($event, $queue)
    {
        $url = 'http://pages.cs.wisc.edu/~ballard/bofh/bofhserver.pl';

        $request = new Request([
            'url' => $url,
            'resolveCallback' =>
                function (Response $response) use ($url, $event, $queue) {
                    $code = $response->getStatusCode();
                    $dom = new \DOMDocument();
                    $dom->loadHTML($response->getBody());
                    $xpath = new \DOMXpath($dom);
                    // XPath to the excuse text
                    $result = $xpath->query('/html/body/center/font[2]');

                    if ($result->length > 0) {
                        $queue->ircPrivmsg($event->getSource(), $result->item(0)->nodeValue);
                    }

                    if ($code !== 200) {
                        $this->getLogger()->notice('[BOFH] Site responded with error', [
                            'code' => $code,
                            'message' => $data['error']['message'],
                        ]);
                        $queue->ircPrivmsg($event->getSource(), 'Sorry, no excuse was found');
                        return;
                    }
                    $this->getLogger()->info('[BOFH] Site successful return');
                },
            'rejectCallback' =>
                function ($data, $headers, $code) use ($event, $queue) {
                    $this->getLogger()->notice('[BOFH] Site failed to respond');
                    $queue->ircPrivmsg($event->getSource(), 'Sorry, there was a problem communicating with the site');
                },
        ]);
        $this->getEventEmitter()->emit('http.request', [$request]);
    }
}
