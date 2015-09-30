<?php

namespace Itscaro\ReactBundle\Command;

use Itscaro\ReactBundle\Reactor\Server;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerRunCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('react:server:run')
            ->setDescription('Run server based on ReactPHP')
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
                'Port where server will be listening.',
                1337
            )
            ->addOption(
                'standalone',
                null,
                InputOption::VALUE_NONE,
                'Enable standalone mode. It means webserver isn\'t needed. Static file will be served by ReactPHP.'
            )
            ->addOption(
                'apc',
                null,
                InputOption::VALUE_NONE,
                'Enable APC cache. --cache option must be enabled.'
            )
            ->addOption(
                'cache',
                null,
                InputOption::VALUE_NONE,
                'Enable basic class loader cache.'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $output->writeln(vsprintf('<info>Server running on port %s:%s.</info>', [$host, $port]));

        $server = new Server($this->getContainer()->getParameter('kernel.root_dir'), $host, $port);

        $server
            ->setEnv($this->getContainer()->getParameter('kernel.environment'))
            ->setStandalone($input->getOption('standalone'))
            ->setApc($input->getOption('apc'))
            ->setCache($input->getOption('cache'))
            ->build()
            ->run();

        $output->writeln('<info>Server stopped.</info>');
    }
}
