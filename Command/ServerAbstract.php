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
    const OPT_SUPERVISOR = 'supervisor';

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
                substr(self::OPT_PORT, 0, 1),
                InputOption::VALUE_OPTIONAL,
                'Port where server will be listening, use comma to separate ports',
                1337
            )->addOption(
                self::OPT_SUPERVISOR,
                '',
                InputOption::VALUE_OPTIONAL,
                'Activate the supervision in main process, any child exited will be recreated',
                false
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 : OK
     */
    protected function start(InputInterface $input, OutputInterface $output)
    {
        declare(ticks = 1);

        $return = 0;

        if (!extension_loaded('pcntl')) {
            $output->writeln('<error>PCNTL PHP extension is not installed or loaded, please enable it before launching server.</error>');
            return 1;
        }

        $host = $input->getOption(self::OPT_HOST);
        $ports = explode(',', $input->getOption(self::OPT_PORT));
        $useSupervisor = filter_var($input->getOption(self::OPT_SUPERVISOR), FILTER_VALIDATE_BOOLEAN);

        // Parent process
        $return = $this->_setUidGid($input, $output);
        if ($return > 0) {
            return $return;
        }

        $parentPid = posix_getpid();
        $childrenPid = [];

        // Fork
        $return = $this->_fork($input, $output, $childrenPid, $host, $ports, $useSupervisor);
        if ($return > 0) {
            return $return;
        }

        // Supervision in parent process
        if ($parentPid == posix_getpid()) {
            $output->writeln(sprintf("My PID: %s. Children PID: %s", $parentPid, implode(' ', array_keys($childrenPid))));

            if ($useSupervisor) {
                $isGoingToTerminate = false;
                pcntl_signal(SIGTERM, function ($signo) use ($input, $output, &$childrenPid, &$isGoingToTerminate) {
                    $output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE ?:
                        $output->writeln("> Received signal {$signo} : my PID " . posix_getpid());
                    switch ($signo) {
                        case SIGTERM:
                            $isGoingToTerminate = true;
                            foreach ($childrenPid as $pid => $config) {
                                $this->_stop($input, $output, $config['host'], [$config['port']]);
                                unset($childrenPid[$pid]);
                            }
                            exit(0);
                    }
                });

                while (true) {
                    $exittedPid = pcntl_waitpid(0, $status, WNOHANG);
                    if ($exittedPid > 0) {
                        $exitCode = pcntl_wexitstatus($status);
                        if ($exitCode == 0) {
                            $output->writeln("Child $exittedPid was exited correctly");
                        } else {
                            $output->writeln("Child $exittedPid was not exited correctly, exit code was: " . $exitCode);
                        }
                        if (!$isGoingToTerminate) {
                            $this->_fork($input, $output, $childrenPid, $childrenPid[$exittedPid]['host'], [$childrenPid[$exittedPid]['port']], $useSupervisor);
                        }
                        unset($childrenPid[$exittedPid]);
                        $output->writeln(sprintf("My PID: %s. Children PID: %s", $parentPid, implode(' ', array_keys($childrenPid))));
                    }
                    pcntl_signal_dispatch();
                    sleep(1);
                }
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

        $this->_stop($input, $output, $host, $ports);

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
        $return = 0;

        $user = $input->getOption(self::OPT_USER);
        $group = $input->getOption(self::OPT_GROUP);

        if ($user !== null) {
            $_user = posix_getpwnam($user);
            if ($_user === false) {
                $output->writeln("<error>User {$user} cannot be found on this system.</error>");
                $return = 2;
            } else {
                // TODO check needed, logs dir should not be done like this!!!
                if (`which chown`) {
                    system("chown -R {$user}" . realpath($this->getContainer()->getParameter('kernel.cache_dir') .
                            DIRECTORY_SEPARATOR . $this->getContainer()->getParameter('kernel.environment')));
                    system("chown -R {$user}" . realpath($this->getContainer()->getParameter('kernel.logs_dir')));
                }
                if (!posix_setuid($_user["uid"])) {
                    $output->writeln("<error>Could not set user {$user} as UID.</error>");
                }
            }
        }

        if ($group !== null) {
            $_group = posix_getgrnam($group);
            if ($_group === false) {
                $output->writeln("<error>Group {$group} cannot be found on this system.</error>");
                $return = 2;
            } else {
                // TODO check needed, logs dir should not be done like this!!!
                if (`which chgrp`) {
                    system("chgrp -R {$user}" . realpath($this->getContainer()->getParameter('kernel.cache_dir') .
                            DIRECTORY_SEPARATOR . $this->getContainer()->getParameter('kernel.environment')));
                    system("chgrp -R {$user}" . realpath($this->getContainer()->getParameter('kernel.logs_dir')));
                }
                if (!posix_setgid($_group["gid"])) {
                    $output->writeln("<error>Could not set group {$group} as GID.</error>");
                }
            }
        }

        return $return;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $childrenPid
     * @param $host
     * @param array $ports
     * @param $useSupervisor
     * @return int
     */
    private function _fork(InputInterface $input, OutputInterface $output, array &$childrenPid, $host, array $ports, $useSupervisor)
    {
        $return = 0;
        foreach ($ports as $port) {
            $pid = pcntl_fork();
            if ($pid > 0) {
                // Parent proccess
                $lock_file = sys_get_temp_dir() . '/react-' . $host . '-' . $port . '.pid';
                $output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE ?:
                    $output->writeln("PID file: {$lock_file}", OutputInterface::VERBOSITY_DEBUG);
                file_put_contents($lock_file, $pid);
                $childrenPid[$pid] = [
                    'host' => $host,
                    'port' => $port,
                ];
            } elseif ($pid < 0) {
                $output->writeln('<error>Child process could not be started. Server is not running.</error>');
                $return = 10;
            } else {
                pcntl_signal(SIGTERM, function ($signo) use ($output) {
                    $output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE ?:
                        $output->writeln("> Received signal {$signo} : my PID " . posix_getpid());
                    switch ($signo) {
                        case SIGTERM:
                            exit(0);
                    }
                });

                // Child process
                if (!$useSupervisor && posix_setsid() < 0) {
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
     * @param $host
     * @param array $ports
     * @return int
     */
    private function _stop(InputInterface $input, OutputInterface $output, $host, array $ports)
    {
        $return = 0;

        foreach ($ports as $port) {
            $lock_file = sys_get_temp_dir() . '/react-' . $host . '-' . $port . '.pid';

            if (!file_exists($lock_file)) {
                $output->writeln(vsprintf('<info>Server on port %s:%s is not running.</info>', [$host, $port]));
                $return = 1;
            }

            $server_pid = file_get_contents($lock_file);
            posix_kill($server_pid, SIGTERM);
            unlink($lock_file);

            $output->writeln(vsprintf('<info>Server on port %s:%s stopped.</info>', [$host, $port]));
        }

        return $return;
    }
}
