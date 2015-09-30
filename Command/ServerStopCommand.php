<?php

namespace Itscaro\ReactBundle\Command;

use Itscaro\ReactBundle\Reactor\ReactKernel;
use React\EventLoop\Factory;
use React\Http\Request;
use React\Http\Response;
use React\Socket\Server as SocketServer;
use React\Http\Server as HttpServer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerStopCommand extends ServerAbstract
{

    protected function configure()
    {
        $this
            ->setName('react:server:stop')
            ->setDescription('Run server based on ReactPHP')
            ->addOption(
                'user',
                '',
                InputOption::VALUE_OPTIONAL,
                'User to run the server.',
                null
            )
            ->addOption(
                'group',
                '',
                InputOption::VALUE_OPTIONAL,
                'Group to run the server.',
                null
            )
            ->addOption(
                'host',
                '',
                InputOption::VALUE_OPTIONAL,
                'Host where server will be listening.',
                '127.0.0.1'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Port of server that will be stopped.',
                1337
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->stop($input, $output);
    }
}
