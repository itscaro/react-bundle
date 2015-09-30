<?php

namespace Itscaro\ReactBundle\Command;

use Itscaro\ReactBundle\Reactor\Server;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ServerAbstract extends ContainerAwareCommand
{
    const OPT_USER = 'user';
    const OPT_GROUP = 'group';
    const OPT_HOST = 'host';
    const OPT_PORT = 'port';
    const OPT_LISTEN = 'listen';
    const OPT_SESSIONLEADER = 'sessionleader';

    protected function configure()
    {
        $this
            ->setName('react:server:restart')
            ->setDescription('Restart background server based on ReactPHP')
            ->addOption(
                self::OPT_USER,
                '',
                InputOption::VALUE_OPTIONAL,
                'User to run the server',
                null
            )
            ->addOption(
                self::OPT_GROUP,
                '',
                InputOption::VALUE_OPTIONAL,
                'Group to run the server',
                null
            )
            ->addOption(
                self::OPT_HOST,
                '',
                InputOption::VALUE_OPTIONAL,
                'Host where server will be listening',
                '127.0.0.1'
            )
            ->addOption(
                self::OPT_PORT,
                'p',
                InputOption::VALUE_OPTIONAL,
                'Port where server will be listening, use comma to separate ports',
                1337
            )->addOption(
                self::OPT_SESSIONLEADER,
                '',
                InputOption::VALUE_OPTIONAL,
                'Promote forked processes to session leader',
                true
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 : OK
     */
    protected function start(InputInterface $input, OutputInterface $output)
    {
        $return = 0;

        if (!extension_loaded('pcntl')) {
            $output->writeln('<error>PCNTL PHP extension is not installed or loaded, please enable it before launching server.</error>');
            return 1;
        }

        $host = $input->getOption(self::OPT_HOST);
        $ports = explode(',', $input->getOption(self::OPT_PORT));
        $sessionleader = filter_var($input->getOption('sessionleader'), FILTER_VALIDATE_BOOLEAN);

        // Parent process
        $return = $this->_setUidGid($input, $output);
        if ($return > 0) {
            return $return;
        }

        // Fork
        foreach($ports as $port) {
            $pid = pcntl_fork();
            if ($pid > 0) {
                $lock_file = sys_get_temp_dir() . '/react-' . $host . '-' . $port . '.pid';
                file_put_contents($lock_file, $pid);
            } elseif ($pid < 0) {
                $output->writeln('<error>Child process could not be started. Server is not running.</error>');
                $return = 10;
            } else {
                // Child process
                if ($sessionleader && posix_setsid() < 0) {
                    $output->writeln('<error>Unable to set the child process as session leader</error>');
                }

                $output->writeln(vsprintf('<info>Server running on port %s:%s.</info>', [$host, $port]));

                $server = new Server($this->getContainer()->getParameter('kernel.root_dir'), $host, $port);
                $server
                    ->setEnv($this->getContainer()->getParameter('kernel.environment'))
                    ->setStandalone($input->getOption('standalone'))
                    ->setApc($input->getOption('apc'))
                    ->setCache($input->getOption('cache'))
                    ->build()
                    ->run();
            }
        }

        return $return;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0: OK
     */
    protected function stop(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getOption(self::OPT_HOST);
        $ports = explode(',', $input->getOption(self::OPT_PORT));

        $return = $this->_setUidGid($input, $output);
        if ($return > 0) {
            return $return;
        }

        foreach($ports as $port) {
            $lock_file = sys_get_temp_dir() . '/react-' . $host . '-' . $port . '.pid';

            if (!file_exists($lock_file)) {
                $output->writeln(vsprintf('<info>Server on port %s:%s is not running.</info>', [$host, $port]));
                return 1;
            }

            $server_pid = file_get_contents($lock_file);
            posix_kill($server_pid, SIGTERM);
            unlink($lock_file);

            $output->writeln(vsprintf('<info>Server on port %s:%s stopped.</info>', [$host, $port]));
        }

        return 0;
    }

    /**
     * Change user:group
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 : OK
     */
    private function _setUidGid(InputInterface $input, OutputInterface $output)
    {
        $user = $input->getOption(self::OPT_USER);
        $group = $input->getOption(self::OPT_GROUP);

        if ($user !== null) {
            $_user = posix_getpwnam($user);
            if ($_user === false) {
                $output->writeln("<error>User {$user} cannot be found on this system.</error>");
                return 2;
            } else {
                if (!posix_setuid($_user["uid"])) {
                    $output->writeln("<error>Could not set user {$user} as UID.</error>");
                }
            }
        }

        if ($group !== null) {
            $_group = posix_getgrnam($group);
            if ($_group === false) {
                $output->writeln("<error>Group {$group} cannot be found on this system.</error>");
                return 2;
            }
            if (!posix_setgid($_group["gid"])) {
                $output->writeln("<error>Could not set group {$group} as GID.</error>");
            }
        }

        return 0;
    }
}
