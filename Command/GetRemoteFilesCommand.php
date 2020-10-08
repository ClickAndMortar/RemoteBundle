<?php

namespace ClickAndMortar\RemoteBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use phpseclib\Net\SFTP;

/**
 * Get files from a remote server (FTP / SSH / ...)
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\Bundle\CatalogBundle\Command
 */
class GetRemoteFilesCommand extends Command
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
     * Excluded filenames for download
     *
     * @var array
     */
    protected $excludedFilenames = [
        '.',
        '..',
    ];

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Get files from a remote server (FTP / SSH / ...)')
             ->addArgument('server', InputArgument::REQUIRED, 'Server')
             ->addArgument('user', InputArgument::REQUIRED, 'User')
             ->addArgument('distantFilePath', InputArgument::REQUIRED, 'Distant file path: /tmp/my_file or /tmp/files_*')
             ->addArgument('localDirectory', InputArgument::REQUIRED, 'Local directory to put remote files')
             ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Connection type', 'sftp')
             ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port', self::DEFAULT_SFTP_CONNECTION_PORT)
             ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete distant files after download')
             ->addOption('password', 'w', InputOption::VALUE_OPTIONAL, 'Password')
             ->addOption('extension', 'x', InputOption::VALUE_OPTIONAL, 'Replace distant files extension with current');
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
        $getterByType   = sprintf('getRemoteFilesBy%s', ucfirst($connectionType));
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
            $input->getArgument('localDirectory'),
            $input->getOption('password'),
            $input->getOption('delete'),
            $input->getOption('extension')
        );
    }

    /**
     * Get remote files from a remote with FTP
     *
     * @param string $server
     * @param string $user
     * @param int    $port
     * @param string $filePath
     * @param string $localDirectory
     * @param null   $password
     * @param bool   $deleteAfterDownload
     * @param null   $newExtension
     *
     * @return void
     */
    protected function getRemoteFilesByFtp($server, $user, $port, $filePath, $localDirectory, $password = null, $deleteAfterDownload = false, $newExtension = null)
    {
        // Start connection
        $connection = ftp_connect($server, $port);
        if ($connection === false) {
            $this->output->writeln(sprintf('<error>Can not open connection to FTP server %s</error>', $server));

            return;
        }

        // Login
        $isLogged = ftp_login($connection, $user, $password);
        if ($isLogged === false) {
            $this->output->writeln(sprintf('<error>Bad user or password to open FTP connection to %s</error>', $server));

            return;
        }

        // Active passive mode
        ftp_set_option($connection, FTP_USEPASVADDRESS, false);
        ftp_pasv($connection, true);

        // Get files list and download
        $files = ftp_nlist($connection, $filePath);
        foreach ($files as $file) {
            $localFile        = sprintf('%s%s', $localDirectory, $file);
            $distantDirectory = pathinfo($filePath, PATHINFO_DIRNAME);
            $distantDirectory = $distantDirectory == '/' ? '' : $distantDirectory;
            $distantFile      = sprintf('%s/%s', $distantDirectory, $file);

            $this->output->writeln(sprintf('<info>Download file %s to %s...</info>', $distantFile, $localFile));
            if (!ftp_get($connection, $localFile, $distantFile, FTP_BINARY)) {
                $this->output->writeln(sprintf('<error>Error during download of file %s</error>', $distantFile));
            }
        }
        ftp_close($connection);
    }

    /**
     * Get remote files from a remote with SFTP
     *
     * @param string $server
     * @param string $user
     * @param int    $port
     * @param string $filePath
     * @param string $localDirectory
     * @param null   $password
     * @param bool   $deleteAfterDownload
     * @param null   $newExtension
     *
     * @return void
     */
    protected function getRemoteFilesBySftp($server, $user, $port, $filePath, $localDirectory, $password = null, $deleteAfterDownload = false, $newExtension = null)
    {
        // Open connection
        $sftpClient = new SFTP($server, $port);
        if (!$sftpClient->login($user, $password)) {
            $this->output->writeln(sprintf('<error>Can not open connection to server %s with user %s</error>', $server, $user));

            return;
        }

        // Open distant directory
        $distantDirectoryName = pathinfo($filePath, PATHINFO_DIRNAME);
        $distantFileMask      = pathinfo($filePath, PATHINFO_BASENAME);
        if (!$sftpClient->chdir($distantDirectoryName)) {
            $this->output->writeln(sprintf('<error>Can not access to distant directory %s</error>', $distantDirectoryName));

            return;
        }

        // Download files
        $hasDownloadedOneFile = false;
        foreach ($sftpClient->rawlist() as $sftpFile) {
            // Manage only classic files
            if ($sftpFile['type'] !== NET_SFTP_TYPE_REGULAR) {
                continue;
            }
            if (fnmatch($distantFileMask, $sftpFile['filename'])) {
                $distantFilename = $sftpFile['filename'];
                $localFilename   = $distantFilename;

                // Add new extension if necessary
                if ($newExtension !== null) {
                    $filenameWithoutExtension = pathinfo($distantFilename, PATHINFO_FILENAME);
                    $localFilename            = sprintf('%s.%s', $filenameWithoutExtension, $newExtension);
                }
                $localFilePath = sprintf('%s%s', $localDirectory, $localFilename);

                $this->output->writeln(sprintf('<info>Download distant file %s...</info>', $distantFilename));
                if (!$sftpClient->get($distantFilename, $localFilePath)) {
                    $this->output->writeln(sprintf('<error>Can not download distant file %s</error>', $distantFilename));
                }
                $hasDownloadedOneFile = true;

                // Delete file if necessary
                if ($deleteAfterDownload) {
                    $sftpClient->delete($distantFilename);
                }
            }
        }

        if ($hasDownloadedOneFile) {
            $this->output->writeln('<info>All files have been successfully downloaded!</info>');
        } else {
            $this->output->writeln('<info>No files to download.</info>');
        }
    }
}
