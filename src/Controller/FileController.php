<?php

namespace App\Controller;

use App\Entity\File;
use App\Form\FileFormType;
use App\Service\Uploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class FileController
 * @package App\Controller
 */
class FileController extends AbstractController
{

    /**
     * @Route("/", name="home")
     * @param Request $request *
     * @param EntityManagerInterface $entityManager
     * @param Uploader $uploader
     * @return Response
     * @throws \Exception
     */
    public function index(Request $request, EntityManagerInterface $entityManager, Uploader $uploader): Response
    {
        $file = new File;
        $form = $this->createForm(FileFormType::class, $file);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /**@var UploadedFile $uploadedFile */
            $uploadedFile = $form['xmlFile']->getData();
            if ($uploadedFile) {
                $newFilename = $uploader->uploadXML($uploadedFile);

                $file->setXmlFileName($newFilename);
                $file->setAddDate(new \DateTime());

                $entityManager->persist($file);
                $entityManager->flush();
            }
        }

        return $this->render('file/index.html.twig', [
            'fileForm' => $form->createView(),
            'controller_name' => 'FileController',
        ]);
    }
}
