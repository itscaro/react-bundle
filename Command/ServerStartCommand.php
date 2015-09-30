<?php

namespace Itscaro\ReactBundle\Command;

use Itscaro\ReactBundle\Reactor\Server;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerStartCommand extends ServerAbstract
{

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('react:server:start')
            ->setDescription('Start background server based on ReactPHP')
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
        return $this->start($input, $output);
    }
}
