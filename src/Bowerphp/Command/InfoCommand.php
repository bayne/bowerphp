<?php

/*
 * This file is part of Bowerphp.
 *
 * (c) Massimiliano Arione <massimiliano.arione@bee-lab.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bowerphp\Command;

use Bowerphp\Bowerphp;
use Bowerphp\Config\Config;
use Bowerphp\Installer\Installer;
use Bowerphp\Package\Package;
use Bowerphp\Repository\GithubRepository;
use Bowerphp\Util\ZipArchive;
use Doctrine\Common\Cache\FilesystemCache;
use Gaufrette\Adapter\Local as LocalAdapter;
use Gaufrette\Filesystem;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Http\Client;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Info
 */
class InfoCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('info')
            ->setDescription('Displays overall information of a package or of a particular version.')
            ->addArgument('package', InputArgument::REQUIRED, 'Choose a package.')
            ->addArgument('property', InputArgument::OPTIONAL, 'A property present in bower.json.')
            ->setHelp(<<<EOT
The <info>info</info> command displays overall information of a package or of a particular version.
If you pass a property present in bower.json, you can get the correspondent value.
EOT
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adapter = new LocalAdapter('/');
        $filesystem = new Filesystem($adapter);
        $httpClient = new Client();
        $config = new Config($filesystem);

        $this->logHttp($httpClient, $output);

        // http cache
        $cachePlugin = new CachePlugin(array(
            'storage' => new DefaultCacheStorage(
                new DoctrineCacheAdapter(
                    new FilesystemCache($config->getCacheDir())
                )
            )
        ));
        $httpClient->addSubscriber($cachePlugin);

        $packageName = $input->getArgument('package');
        $property = $input->getArgument('property');

        $v = explode("#", $packageName);
        $packageName = isset($v[0]) ? $v[0] : $packageName;
        $version = isset($v[1]) ? $v[1] : "*";

        $package = new Package($packageName, $version);
        $bowerphp = new Bowerphp($config);
        $installer = new Installer($filesystem, $httpClient, new GithubRepository(), new ZipArchive(), $config, $output);

        $bower = $bowerphp->getPackageInfo($package, $installer, 'bower');
        if ($version == '*') {
            $versions = $bowerphp->getPackageInfo($package, $installer, 'versions');
        }

        // TODO add colors!
        $output->writeln($bower);
        if ($version == '*') {
            $output->writeln('Available versions:');
            foreach ($versions as $v) {
                $output->writeln("- $v");
            }
        }
    }
}
