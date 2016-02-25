<?php
namespace App\Controller;

use GitChecker\GitWrapper\GitWrapper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Container;
use Slim\Views\Twig;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DirectoryController
{
    /**
     * @var Twig
     */
    protected $view;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->view = $container->get('view');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function index(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->depth(0)
            ->sortByName()
            ->in($root);

        $this->view->render(
            $response,
            'index.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'directories' => $finder,
            ]
        );

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function show(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $path = trim($arguments['path'], '/\\') . DIRECTORY_SEPARATOR;
        $absolutePath = $root . $path;

        if (!@is_dir($absolutePath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1456264866695);
        }

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->followLinks()
            ->name('.git')
            ->depth('< 4')
            ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strcmp($a->getRelativePath(), $b->getRelativePath());
            })
            ->in($absolutePath);

        $gitWrapper = new GitWrapper();
        if (!empty($settings['git-wrapper']['git-binary'])) {
            $gitWrapper->setGitBinary($settings['git-wrapper']['git-binary']);
        }
        $repositories = [];
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $relativePath = trim($directory->getRelativePath(), '/\\');
            $gitRepository = $gitWrapper->getRepository($absolutePath . $relativePath . DIRECTORY_SEPARATOR);
            $repositories[] = [
                'relativePath' => $relativePath,
                'status' => $gitRepository->getStatus(),
                'trackingInformation' => $gitRepository->getTrackingInformation(),
            ];
        }

        $this->view->render(
            $response,
            'show.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'path' => $path,
                'repositories' => $repositories,
            ]
        );

        return $response;
    }
}
