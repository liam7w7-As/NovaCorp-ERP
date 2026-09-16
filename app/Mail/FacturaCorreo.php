<?php

namespace App\Mail;

use App\Models\FacturaElectronica;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FacturaCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FacturaElectronica $factura,
        public string $motivo = 'emitida', // emitida | anulada
    ) {}

    public function envelope(): Envelope
    {
        $n = $this->factura->numero_factura;

        return new Envelope(
            subject: $this->motivo === 'anulada'
                ? "Factura {$n} anulada - GISECA SRL"
                : "Factura electrónica {$n} - GISECA SRL",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura',
            with: [
                'factura' => $this->factura,
                'motivo' => $this->motivo,
            ],
        );
    }

    public function attachments(): array
    {
        $adjuntos = [];
        if ($this->factura->pdf_path && Storage::disk('public')->exists($this->factura->pdf_path)) {
            $adjuntos[] = Attachment::fromStorageDisk('public', $this->factura->pdf_path)
                ->as($this->factura->numero_factura.'.pdf');
        }
        if ($this->factura->xml_firmado) {
            $adjuntos[] = Attachment::fromData(
                fn () => $this->factura->xml_firmado,
                $this->factura->numero_factura.'.xml'
            )->withMime('application/xml');
        }

        return $adjuntos;
    }
}
