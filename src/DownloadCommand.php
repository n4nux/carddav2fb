<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DownloadCommand extends Command
{
    use ConfigTrait;

    const JSON_OPTIONS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;

    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Load from CardDAV server')
            ->addArgument('filename', InputArgument::OPTIONAL, 'raw vcards json file')
            ->addOption('image', 'i', InputOption::VALUE_NONE, 'download images')
            ->addOption('raw', 'r', InputOption::VALUE_REQUIRED, 'export raw vcards to json file');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $server = $this->config['server'];
        $backend = backendProvider($server);
        $progress = new ProgressBar($output);

        if ($inputFile = $input->getArgument('filename')) {
            // read from file
            $vcards = json_decode(file_get_contents($inputFile));
        }
        else {
            // download
            error_log("Downloading vcards");

            $progress->start();
            $vcards = download($backend, function () use ($progress) {
                $progress->advance();
            });
            $progress->finish();

            error_log(sprintf("\nDownloaded %d vcard(s)", count($vcards)));

            if ($file = $input->getOption('json')) {
                $json = json_encode($vcards, self::JSON_OPTIONS);
                file_put_contents($file, $json);
            }
        }

        // parsing
        error_log("Parsing vcards");

        $cards = parse($vcards);
        $json = json_encode($cards, self::JSON_OPTIONS);

        // images
        if ($input->getOption('image')) {
            error_log("Downloading images");

            $progress->start();
            $cards = downloadImages($backend, $cards, function() use ($progress) {
                $progress->advance();
            });
            $progress->finish();

            error_log(sprintf("\nDownloaded %d image(s)", countImages($cards)));
        }

        $json = json_encode($cards, self::JSON_OPTIONS);
        echo $json;
    }
}