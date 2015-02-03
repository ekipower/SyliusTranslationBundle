<?php
/**
 * This file is part of the EkiSyliusTranslationBundle package.
 *
 * (c) EkiPower <http://github.com/ekipower>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */ 

namespace Eki\Sylius\SyliusTranslationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Config\Resource\FileResource;

use \RuntimeException;

/**
* @author Nguyen Tien Hy ngtienhy@gmail.com
* 
*/
class MergeTranslationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('eki:sylius:translation:merge')
            ->setDescription('Merge all translations for Sylius.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Sylius translation merging...</info>');
        $output->writeln('');

		$translates = $this->scanningStep($input, $output);
		if ( $this->mergingStep($input, $output, $translates) === false )
		{
			$output->writeln('<error>Error when merging Sylius translation files</error>');
		}
		else
		{
			$output->writeln('<info>Successfully merging Sylius translation files</info>');
		}
    }
	
	/**
	* Scan to prepare information
	* 
	* @param InputInterface $input
	* @param OutputInterface $output
	*
	* @return array 
	*/
	private function scanningStep(InputInterface $input, OutputInterface $output)
	{
		$rootSourceDir = __DIR__ . '/../Resources/translations/Bundle';

		$translates = array();

		$filterYaml = function (\SplFileInfo $file)
		{
			return $file->getExtension() === 'yml';
		};

		$output->writeln('<info>=== Scanning to preparing ...</info>');
		
		$finder = new Finder();
		$finder->directories()->in( $rootSourceDir )->depth('== 0');
		foreach ($finder as $dir) 
		{
			$output->writeln('<info>Scanning in directory ' . $dir->getPathname() . '</info>');

			$finder1 = new Finder();
			$finder1->files()->filter($filterYaml)->in( $dir->getPathname() . '/Resources/translations' );
			foreach ($finder1 as $file)
			{
				$output->writeln('<info>File ' . $file->getBasename() . '</info>');
				
				$baseName = $file->getBasename();
				
				$output->writeln('<comment>Basename ' . $baseName . '</comment>');
				
				if ( false === ( $firstPointPos = strpos($baseName, '.') ) )
				{
					$output->writeln('<warn>Invalid filename when scanning domain.</warn>');
					continue;
				}

				$restName = substr($baseName, $firstPointPos + 1);

				$output->writeln('<comment>Rest name ' . $restName . '</comment>');

				if ( false === ( $secondPointPos = strpos($restName, '.') ) )
				{
					$output->writeln('<warn>Invalid filename when scanning locale.</warn>');
					continue;
				}
		
				$domain = substr($baseName, 0, $firstPointPos);

				$output->writeln('<comment>Get domain ' . $domain . '</comment>');

				if ( !in_array( $domain, array_keys( $translates ) ) )
				{
					$output->writeln('<comment>Preparing array domain ' . $domain . '</comment>');
					
					$translates[$domain] = array();
				}

				$locale = substr($restName, 0, $secondPointPos);

				$output->writeln('<comment>Get locale ' . $locale . '</comment>');

				if ( !in_array( $locale, array_keys( $translates[$domain] ) ) )
				{
					$output->writeln('<comment>Preparing array domain ' . $domain . ' for locale ' . $locale .'</comment>');

					$translates[$domain][$locale] = array();
				}
				
				$translates[$domain][$locale][] = $file->getPathname();
			}
		}

		return $translates;
	}

	/**
	* Merge all translation files
	* 
	* @param InputInterface $input
	* @param OutputInterface $output
	* @param array $translates Translation files information
	*
	* @return array 
	*/
	private function mergingStep(InputInterface $input, OutputInterface $output, array $translates)
	{
		$output->writeln('<info>=== Merging ...</info>');

		$destDir = __DIR__ . '/../Resources/translations';
		
		$translatedFiles = array();

		$parser = new Parser();
		$dumper = new Dumper();

		foreach($translates as $domain => $translate)
		{
			foreach($translate as $locale => $translateFiles)
			{
				$yamls = array();

		        $output->writeln('<info>Merging in domain ' . $domain .' with locale ' . $locale . '</info>');
		
				foreach($translateFiles as $translateFilename)
				{
					$output->writeln('<info>File ' . $translateFilename . '</info>');
					
					try
					{
						$yaml = $parser->parse(file_get_contents( $translateFilename ));
					}
					catch(ParseException $e)
					{
						$output->writeln('<error>Unable to parse YAML file ' . $translateFilename . ': ' . $e->getMessage() . '</error>');
						return false;		
					}

					if ( !empty( $yaml ) )
					{
						$yamls = array_merge_recursive($yamls, $yaml);
					}
				}

				$translatedFilename = $destDir . '/' . $domain . '.' . $locale . '.yml';
				file_put_contents( $translatedFilename , $dumper->dump( $yamls, 10 ) );
				$translatedFiles[$domain . '.' . $locale] = $translatedFilename;
			}
		}
		
        $output->writeln('<info>All translations have been successfully merged.</info>');
		
		return $translatedFiles;
	}	
}
