<?php

namespace ClickAndMortar\RemoteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Get files from a remote server (FTP / SSH / ...)
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\Bundle\CatalogBundle\Command
 */
class GetRemoteFilesCommand extends ContainerAwareCommand
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
        $this->setName('candm:remote:get')
             ->setDescription('Get files from a remote server (FTP / SSH / ...)')
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
     * Update products shipping costs linked to a shipping cost range entity
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
        $connection = ssh2_connect($server, $port);
        ssh2_auth_password($connection, $user, $password);
        $sftpConnection = ssh2_sftp($connection);
        $sftpBasePath   = intval($sftpConnection);

        $filesToDownload      = [];
        $distantDirectoryName = pathinfo($filePath, PATHINFO_DIRNAME);
        $distantFileMask      = pathinfo($filePath, PATHINFO_BASENAME);
        $sftpPath             = sprintf('ssh2.sftp://%s%s', $sftpBasePath, $distantDirectoryName);
        $handle               = opendir($sftpPath);
        while (false != ($distantFilename = readdir($handle))) {
            $basename = pathinfo($distantFilename, PATHINFO_BASENAME);
            if (
                fnmatch($distantFileMask, $distantFilename)
                && !in_array($basename, $this->excludedFilenames)
            ) {
                $localFilename = $distantFilename;
                if ($newExtension !== null) {
                    $filenameWithoutExtension = pathinfo($distantFilename, PATHINFO_FILENAME);
                    $localFilename            = sprintf('%s.%s', $filenameWithoutExtension, $newExtension);
                }

                $filesToDownload[] = [
                    'distant' => sprintf('%s/%s', $distantDirectoryName, $distantFilename),
                    'local'   => sprintf('%s%s', $localDirectory, $localFilename),
                ];
            }
        }
        closedir($handle);

        foreach ($filesToDownload as $fileToDownload) {
            $this->output->writeln(sprintf('<info>Download file %s to %s...</info>', $fileToDownload['distant'], $fileToDownload['local']));

            // Open distant file
            $remoteFilePath = sprintf('ssh2.sftp://%s%s', $sftpBasePath, $fileToDownload['distant']);
            if (!$remoteFile = @fopen($remoteFilePath, 'r')) {
                $this->output->writeln(sprintf('<error>Can not open distant file %s</error>', $fileToDownload['distant']));
                continue;
            }

            // Open local file
            if (!$localFile = @fopen($fileToDownload['local'], 'w')) {
                $this->output->writeln(sprintf('<error>Can not open local file %s</error>', $fileToDownload['local']));
                continue;
            }

            // And write file
            $read            = 0;
            $distantFileSize = filesize($remoteFilePath);
            while ($read < $distantFileSize && ($buffer = fread($remoteFile, $distantFileSize - $read))) {
                $read += strlen($buffer);
                if (fwrite($localFile, $buffer) === false) {
                    $this->output->writeln(sprintf('<error>Can not write local file %s</error>', $fileToDownload['local']));
                    break;
                }
            }
            fclose($localFile);
            fclose($remoteFile);

            // Delete file if necessary
            if ($deleteAfterDownload) {
                ssh2_sftp_unlink($sftpConnection, $fileToDownload['distant']);
            }
        }

        if (!empty($filesToDownload)) {
            $this->output->writeln('<info>All files have been successfully downloaded!</info>');
        } else {
            $this->output->writeln('<info>No files to download.</info>');
        }
    }
}
