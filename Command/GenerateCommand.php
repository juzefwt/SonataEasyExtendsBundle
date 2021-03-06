<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Sonata\EasyExtendsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\DoctrineBundle\Command\DoctrineCommand;

use Symfony\Bundle\FrameworkBundle\Generator\Generator;
use Sonata\EasyExtendsBundle\Bundle\BundleMetadata;

/**
 * Generate Application entities from bundle entities
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class GenerateCommand extends DoctrineCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('sonata:easy-extends:generate')
            ->setHelp(<<<EOT
The <info>easy-extends:generate:entities</info> command generates a set of Entities
in your Application Entity folder from the Entities set in bundles. This command
will allow to generate create custom code from the model.

  <info>./symfony easy-extends:generate SonataUserBundle</info>
EOT
        );

        $this->addArgument('bundle', InputArgument::OPTIONAL, 'The bundle name to "easy-extends"', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // find a better way to detect the Application folder
        $configuration = array(
            'application_dir' => sprintf("%s/Application", $this->getContainer()->get('kernel')->getRootDir())
        );

        $bundleName = $input->getArgument('bundle');

        if ($bundleName == false) {
            $output->writeln('');
            $output->writeln('<error>You must provide a bundle name!</error>');
            $output->writeln('');
            $output->writeln('  Bundles availables :');
            foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
                $bundleMetadata = new BundleMetadata($bundle, $configuration);

                if (!$bundleMetadata->isExtendable()) {
                    continue;
                }

                $output->writeln(sprintf('     - %s', $bundle->getName()));
            }

            $output->writeln('');

            return;
        }

        $processed = false;
        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {

            if ($bundle->getName() != $bundleName) {
                continue;
            }

            $processed = true;
            $bundleMetadata = new BundleMetadata($bundle, $configuration);

            // generate the bundle file
            if(!$bundleMetadata->isExtendable()) {
                $output->writeln(sprintf('Ignoring bundle : "<comment>%s</comment>"', $bundleMetadata->getClass()));
                continue;
            }

            // generate the bundle file
            if(!$bundleMetadata->isValid()) {
                $output->writeln(sprintf('%s : <comment>wrong folder structure</comment>', $bundleMetadata->getClass()));
                continue;
            }

            $output->writeln(sprintf('Processing bundle : "<info>%s</info>"', $bundleMetadata->getName()));

            $this->getContainer()->get('sonata.easy_extends.generator.bundle')
                ->generate($output, $bundleMetadata);

            $output->writeln(sprintf('Processing orm : "<info>%s</info>"', $bundleMetadata->getName()));
            $this->getContainer()->get('sonata.easy_extends.generator.orm')
                ->generate($output, $bundleMetadata);

            $output->writeln(sprintf('Processing odm : "<info>%s</info>"', $bundleMetadata->getName()));
            $this->getContainer()->get('sonata.easy_extends.generator.odm')
                ->generate($output, $bundleMetadata);

            $output->writeln('');
        }

        if ($processed) {
            $output->writeln('done!');
            return true;
        }

        $output->writeln(sprintf('<error>The bundle \'%s\' does not exist or not defined in the kernel file!</error>', $bundleName));

        return false;
    }
}