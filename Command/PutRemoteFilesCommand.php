<?php

namespace ClickAndMortar\RemoteBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use phpseclib\Net\SFTP;

/**
 * Put files to a remote server (FTP / SSH / ...)
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\Bundle\CatalogBundle\Command
 */
class PutRemoteFilesCommand extends Command
{
    /**
     * Default SFTP connection port
     *
     * @var int
     */
    const DEFAULT_SFTP_CONNECTION_PORT = 22;

    /**
     * Output interface
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Put files to a remote server (FTP / SSH / ...)')
             ->addArgument('server', InputArgument::REQUIRED, 'Server')
             ->addArgument('user', InputArgument::REQUIRED, 'User')
             ->addArgument('localFilePath', InputArgument::REQUIRED, 'Local file path: /tmp/my_file_*.txt')
             ->addArgument('distantFilePath', InputArgument::REQUIRED, 'Distant file path: /tmp/my_file.txt')
             ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Connection type', 'sftp')
             ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port', self::DEFAULT_SFTP_CONNECTION_PORT)
             ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete local files after upload')
             ->addOption('password', 'w', InputOption::VALUE_OPTIONAL, 'Password');
    }

    /**
     * Execute command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Check connection type
        $connectionType = $input->getOption('type');
        $getterByType   = sprintf('putRemoteFilesBy%s', ucfirst($connectionType));
        if (!method_exists($this, $getterByType)) {
            $output->writeln('<error>Invalid connection type.</error>');

            return;
        }
        $this->output = $output;
        $this->$getterByType(
            $input->getArgument('server'),
            $input->getArgument('user'),
            $input->getOption('port'),
            $input->getArgument('distantFilePath'),
            $input->getArgument('localFilePath'),
            $input->getOption('password'),
            $input->getOption('delete')
        );
    }

    /**
     * Put local file to a remote with SFTP
     *
     * @param string $server
     * @param string $user
     * @param int    $port
     * @param string $distantFilePath
     * @param string $localFilePath
     * @param null   $password
     * @param bool   $deleteAfterUpload
     *
     * @return void
     */
    protected function putRemoteFilesBySftp($server, $user, $port, $distantFilePath, $localFilePath, $password = null, $deleteAfterUpload = false)
    {
        // Check for local files
        $localFilePaths = glob($localFilePath);
        if (!is_array($localFilePaths)) {
            $this->output->writeln('<error>No local files matching path.</error>');

            return;
        }

        // Open connection
        $sftpClient = new SFTP($server, $port);
        if (!$sftpClient->login($user, $password)) {
            $this->output->writeln(sprintf('<error>Can not open connection to server %s with user %s</error>', $server, $user));

            return;
        }

        // Upload files
        $isDistantDirectoryPath = substr($distantFilePath, -1) == DIRECTORY_SEPARATOR;
        foreach ($localFilePaths as $localFilePath) {
            if ($isDistantDirectoryPath) {
                $distantFilePath = sprintf('%s%s', $distantFilePath, basename($localFilePath));
            }
            $this->output->writeln(sprintf('<info>Update local file %s...</info>', $localFilePath));
            if (!$sftpClient->put($distantFilePath, $localFilePath, SFTP::SOURCE_LOCAL_FILE)) {
                $this->output->writeln(sprintf('<error>Can not upload local file %s</error>', $localFilePath));
            }
            if ($deleteAfterUpload) {
                unlink($localFilePath);
            }
        }
    }
}
