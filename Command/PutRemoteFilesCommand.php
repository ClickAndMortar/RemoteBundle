<?php

namespace ClickAndMortar\RemoteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Put files to a remote server (FTP / SSH / ...)
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\Bundle\CatalogBundle\Command
 */
class PutRemoteFilesCommand extends ContainerAwareCommand
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
        $this->setName('candm:remote:put')
             ->setDescription('Put files to a remote server (FTP / SSH / ...)')
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
        $localFilePaths = glob($localFilePath);
        if (!is_array($localFilePaths)) {
            $this->output->writeln('<error>No local files matching path.</error>');

            return;
        }

        foreach ($localFilePaths as $localFilePath) {
            if (!file_exists($localFilePath)) {
                $this->output->writeln(sprintf('<error>Invalid local file path : %s</error>', $localFilePath));

                return;
            }

            // Init SFTP connection
            $connection = ssh2_connect($server, $port);
            ssh2_auth_password($connection, $user, $password);
            $sftpConnection = ssh2_sftp($connection);
            $sftpBasePath   = intval($sftpConnection);

            // Open distant file
            $sftpDistantPath = sprintf('ssh2.sftp://%s%s', $sftpBasePath, $distantFilePath);
            $distantFile     = fopen($sftpDistantPath, 'w');
            if ($distantFile === false) {
                $this->output->writeln('<error>Can not write distant file.</error>');

                return;
            }

            // And local file
            $localFile = fopen($localFilePath, 'r');
            if ($localFile === false) {
                $this->output->writeln('<error>Can not read local file.</error>');

                return;
            }

            // And copy
            $writtenBytes = stream_copy_to_stream($localFile, $distantFile);
            if ($writtenBytes === false) {
                $this->output->writeln('<error>Can not copy file.</error>');

                return;
            }

            fclose($distantFile);
            fclose($localFile);

            // Delete local file if necessary
            if ($deleteAfterUpload) {
                unlink($localFilePath);
            }
        }
    }
}
