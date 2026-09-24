<style>
    @page { size: A4 portrait; margin: 8mm; }
    .report-card-page {
        width: 210mm;
        min-height: 297mm;
        margin: 1.5rem auto;
        padding: 10mm;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .12);
    }
    @media print {
        .no-print { display: none !important; }
        body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .report-card-page { margin: 0; padding: 0; width: auto; min-height: 0; box-shadow: none; page-break-after: always; }
        .report-card-page:last-child { page-break-after: auto; }
    }
    @media screen and (max-width: 820px) {
        .report-card-page { width: auto; min-height: 0; padding: 12px; margin: 12px; }
    }
</style>
