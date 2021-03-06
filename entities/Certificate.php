<?php

namespace PitouFW\Entity;

use Exception;
use PitouFW\Model\CertificateModel;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use chillerlan\QRCode\QRCode;

class Certificate {
    const REASON_WORK = 'travail';
    const REASON_GROCERIES = 'achats';
    const REASON_HEALTH = 'sante';
    const REASON_FAMILY = 'famille';
    const REASON_DISABILITY = 'handicap';
    const REASON_WORKOUT = 'sport_animaux';
    const REASON_LEGAL = 'convocation';
    const REASON_PUBLIC_INTEREST = 'missions';
    const REASON_CHILDREN = 'enfants';

    const Y_REASON = [
        self::REASON_WORK => 94,
        self::REASON_GROCERIES => 110,
        self::REASON_HEALTH => 130,
        self::REASON_FAMILY => 144,
        self::REASON_DISABILITY => 158,
        self::REASON_WORKOUT => 172,
        self::REASON_LEGAL => 194,
        self::REASON_PUBLIC_INTEREST => 208,
        self::REASON_CHILDREN => 224
    ];

    private ?Citizen $citizen = null;
    private ?Fpdi $pdf = null;

    private ?string $reason = null;
    private string $made_in = '';
    private int $made_at = 0;

    private string $filename = '';

    /**
     * Attestation constructor.
     */
    public function __construct() {
        $this->pdf = new Fpdi();
        $this->pdf->SetAuthor("Ministère de l'intérieur");
        $this->pdf->AddPage();

        try {
            $this->pdf->setSourceFile(ROOT . 'attestation.pdf');
        } catch (PdfParserException $e) {
            die('impossible de parser le PDF');
        }

        try {
            $tpl = $this->pdf->importPage(1);
        } catch (Exception $e) {
            die('erreur lors de l\'import de la page');
        }

        $this->pdf->useTemplate($tpl);
        $this->made_at = time() + (JET_LAG * 3600);
    }

    /**
     * @return $this
     */
    public function generate(): Certificate {
        $this->_handleCitizen()
            ->_handleReason()
            ->_handleTimestamp()
            ->_sign();

        return $this;
    }

    public function show() {
        $this->pdf->Output('I', $this->filename);
    }

    public function download() {
        $this->pdf->Output('D', $this->filename);
    }

    /**
     * @return Certificate
     */
    private function _handleCitizen(): Certificate {
        if ($this->citizen === null) {
            die('données incomplètes');
        }

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Text(42, 51, CertificateModel::handleUTF8($this->citizen->getFirstname() . ' ' . $this->citizen->getLastname()));
        $this->pdf->Text(42, 59, $this->citizen->getBirthDate());
        $this->pdf->Text(106, 59, CertificateModel::handleUTF8($this->citizen->getBirthLocation()));
        $this->pdf->Text(47, 67, CertificateModel::handleUTF8($this->citizen->getStreetAddress()));

        return $this;
    }

    /**
     * @return Certificate
     */
    private function _handleReason(): Certificate {
        if (!isset(self::Y_REASON[$this->reason])) {
            die('Raison invalide');
        }

        $this->pdf->SetFont('Helvetica', 'B', 20);
        $this->pdf->Text(29, self::Y_REASON[$this->reason], 'X');

        return $this;
    }

    /**
     * @return Certificate
     */
    private function _handleTimestamp(): Certificate {
        if ($this->reason === null || $this->made_in === '' || $this->made_at <= 0) {
            die('données incomplètes');
        }

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Text(38, 235, CertificateModel::handleUTF8($this->made_in));
        $this->pdf->Text(33, 243, date('d/m/Y', $this->made_at));
        $this->pdf->Text(91, 243, date('H:i', $this->made_at));

        $this->filename = 'attestation-' . date('Y-m-d_H-i', $this->made_at) . '.pdf';

        return $this;
    }

    /**
     * @return Certificate
     */
    private function _sign(): Certificate {
        $qrcode = new QRCode();
        $imgUrl = $qrcode->render(
            'Cree le: ' . date('d/m/Y \a H\hi', $this->made_at) . ";\n" .
            'Nom: ' . $this->citizen->getLastname() . ";\n" .
            'Prenom: ' . $this->citizen->getFirstname() . ";\n" .
            'Naissance: ' . $this->citizen->getBirthDate() . ' a ' . $this->citizen->getBirthLocation() . ";\n" .
            'Adresse: ' . $this->citizen->getStreetAddress() . ";\n" .
            'Sortie: ' . date('d/m/Y \a H:i', $this->made_at) . ";\n" .
            'Motifs: ' . $this->reason
        );
        $this->pdf->Image($imgUrl, 150, 223, 40, 40, 'png');
        $this->pdf->AddPage();
        $this->pdf->Image($imgUrl, 7, 7, 120, 120, 'png');

        return $this;
    }

    /**
     * @param Citizen|null $citizen
     * @return Certificate
     */
    public function setCitizen(?Citizen $citizen): Certificate {
        $this->citizen = $citizen;
        return $this;
    }

    /**
     * @param string|null $reason
     * @return Certificate
     */
    public function setReason(?string $reason): Certificate {
        $this->reason = $reason;
        return $this;
    }

    /**
     * @param string $made_in
     * @return Certificate
     */
    public function setMadeIn(string $made_in): Certificate {
        $this->made_in = $made_in;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename(): string {
        return $this->filename;
    }
}
