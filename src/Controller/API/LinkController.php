<?php

namespace App\Controller\API;

use App\Entity\Link;
use App\Repository\LinksRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class LinkController extends AbstractController
{
    #[Route("/api/links", methods: ["GET"])]
    public function index(LinksRepository $linksRepository): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $links = $linksRepository->findAll();
        return $this->json($links);
    }

    #[Route("/api/links", methods: ["POST"])]
    public function create(Request $request, LinksRepository $linksRepository, SluggerInterface $slugger): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? null;

        if (!$url) {
            return $this->json(['error' => 'URL is required'], Response::HTTP_BAD_REQUEST);
        }

        // Générer un slug unique basé sur l'URL
        $slug = $slugger->slug($url)->lower();
        if ($slug === '') {
            return $this->json(['error' => 'Invalid slug generated'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $uniqueSlug = $slug;
        $counter = 1;

        // Vérifier si le slug existe déjà dans la base de données et générer un nouveau slug s'il n'est pas unique
        while ($linksRepository->findOneBy(['slug' => $uniqueSlug])) {
            $uniqueSlug = $slug . '-' . $counter;
            $counter++;
        }

        $link = new Link();
        $link->setUrl($url);
        $link->setSlug($uniqueSlug);

        $linksRepository->save($link, true);

        return $this->json($link, Response::HTTP_CREATED);
    }

    #[Route("/api/short-links/{id}", methods: ["GET"])]
    public function getShortLink($id, LinksRepository $linksRepository): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $link = $linksRepository->find($id);

        // Vérifier si le lien existe
        if (!$link) {
            return $this->json(['error' => 'Link not found'], \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);
        }

        // Ajouter des logs pour déboguer
        error_log('Link slug: ' . $link->getSlug());

        $shortLink = [
            'slug' => $link->getSlug() ?? '',
        ];

        return $this->json($shortLink);
    }

    #[Route("/api/short-links/{id}", methods: ["DELETE"])]
    public function deleteShortLink($id, LinksRepository $linksRepository, EntityManagerInterface $entityManager): Response
    {
        $link = $linksRepository->find($id);

        // Vérifier si le lien existe
        if (!$link) {
            return new Response('Link not found', Response::HTTP_NOT_FOUND);
        }

        // Suppression du lien
        $entityManager->remove($link);
        $entityManager->flush();

        // Retourner une réponse de succès
        return new Response('Link deleted successfully', Response::HTTP_NO_CONTENT);
    }

    #[Route("/api/short-links/{id}", methods: ["PUT"])]
    public function updateLink($id, Request $request, LinksRepository $linksRepository, EntityManagerInterface $entityManager): Response
    {
        // Récupérer le lien à partir de l'id
        $link = $linksRepository->find($id);

        // Vérifier si le lien existe
        if (!$link) {
            return new Response('Link not found', Response::HTTP_NOT_FOUND);
        }

        // Récupérer les données du corps de la requête
        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs autorisés
        if (isset($data['titre'])) {
            $link->setTitre($data['titre']);
        }

        if (isset($data['description'])) {
            $link->setDescription($data['description']);
        }

        // Les champs 'url' et 'slug' ne doivent pas être modifiés
        // Donc, on ne fait rien pour ces champs

        // Sauvegarder les modifications
        $entityManager->persist($link);
        $entityManager->flush();

        return new Response('Link updated successfully', Response::HTTP_OK);
    }

    #[Route("/r/{slug}", methods: ["GET"])]
    public function redirectToOriginalLink($slug, LinksRepository $linksRepository): Response
    {
        // Récupérer le lien correspondant au slug
        $link = $linksRepository->findOneBy(['slug' => $slug]);

        // Vérifier si le lien existe
        if (!$link) {
            return new Response('Link not found', Response::HTTP_NOT_FOUND);
        }

        // Redirection vers l'URL d'origine
        return $this->redirect($link->getUrl());
    }
}
