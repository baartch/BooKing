<?php
/**
 * SUISA live programme form export.
 *
 * Fills the tokenized ODT template in assets/suisa_template/ with data
 * from a show, its linked venue, and the assigned setlist, and returns
 * the finished document as ODT bytes.
 *
 * Template tokens (assets/suisa_template/content.xml, see
 * dev_helpers/build_suisa_template.py):
 *   {{VENUE_NAME}} {{VENUE_STREET}} {{VENUE_CITYLINE}} {{VENUE_CAPACITY}}
 *   {{VENUE_TYPE}} {{SHOW_DATE}} {{TEAM_NAME}}
 *   {{SONG_NUMBER}} {{SONG_TITLE}} {{SONG_COMPOSER}} {{SONG_ARRANGER}}
 *   {{SONG_COUNT}} {{SONG_DURATION}}
 * The single template song row is duplicated once per setlist song.
 * The genre ("Liedermacher") is baked into the template as static text.
 */

require_once __DIR__ . '/../core/zip_writer.php';

const SUISA_GENRE = 'Liedermacher';

function suisaXmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
}

function formatSuisaDuration(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '';
    }

    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function formatSuisaDate(string $isoDate): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) {
        return '';
    }

    [$year, $month, $day] = [intval($matches[1]), intval($matches[2]), intval($matches[3])];
    if (!checkdate($month, $day, $year)) {
        return '';
    }

    return sprintf('%02d.%02d.%04d', $day, $month, $year);
}

function buildSuisaCityLine(array $venue): string
{
    $country = trim((string) ($venue['country'] ?? ''));
    $postal = trim((string) ($venue['postal_code'] ?? ''));
    $city = trim((string) ($venue['city'] ?? ''));

    $line = $postal;
    if ($country !== '') {
        $line = $line !== '' ? $country . '-' . $line : $country;
    }
    if ($city !== '') {
        $line = $line !== '' ? $line . ' ' . $city : $city;
    }

    return $line;
}

function suisaDownloadFilename(array $show): string
{
    $name = trim((string) ($show['name'] ?? ''));
    if ($name === '') {
        $name = 'Show';
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);

    // Filename date prefix: yyyy.mm.dd (sorted naturally by date).
    $datePrefix = '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) ($show['show_date'] ?? ''), $matches)
        && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
    ) {
        $datePrefix = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '_';
    }

    return $datePrefix . 'SUISA_' . $safeName . '.odt';
}

/**
 * Collect the template directory as an ordered entries map
 * (mimetype first) for the zip builder.
 *
 * @return array<string, string>
 */
function suisaTemplateEntries(string $templateDir): array
{
    if (!is_dir($templateDir)) {
        throw new RuntimeException('SUISA template directory missing: ' . $templateDir);
    }

    $entries = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templateDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($templateDir))), '/');
        $entries[$relative] = (string) file_get_contents((string) $file->getPathname());
    }

    return $entries;
}

/**
 * Replace the single template song row with one filled row per song.
 */
function fillSuisaSongRows(string $templateXml, array $songs): string
{
    $tokenPos = strpos($templateXml, '{{SONG_TITLE}}');
    if ($tokenPos === false) {
        throw new RuntimeException('SUISA template has no song row template.');
    }

    $rowStart = strrpos(substr($templateXml, 0, $tokenPos), '<table:table-row');
    $rowEnd = strpos($templateXml, '</table:table-row>', $tokenPos);
    if ($rowStart === false || $rowEnd === false) {
        throw new RuntimeException('SUISA template song row is malformed.');
    }
    $rowEnd += strlen('</table:table-row>');

    $rowTemplate = substr($templateXml, $rowStart, $rowEnd - $rowStart);

    $rows = '';
    foreach (array_values($songs) as $index => $song) {
        $rows .= strtr($rowTemplate, [
            '{{SONG_NUMBER}}' => (string) ($index + 1),
            '{{SONG_TITLE}}' => suisaXmlEscape((string) ($song['name'] ?? '')),
            '{{SONG_COMPOSER}}' => suisaXmlEscape((string) ($song['composer'] ?? '')),
            '{{SONG_ARRANGER}}' => suisaXmlEscape((string) ($song['arranger'] ?? '')),
            '{{SONG_COUNT}}' => '1',
            '{{SONG_DURATION}}' => suisaXmlEscape(formatSuisaDuration(
                isset($song['duration_seconds']) ? (int) $song['duration_seconds'] : null
            )),
        ]);
    }

    return substr($templateXml, 0, $rowStart) . $rows . substr($templateXml, $rowEnd);
}

/**
 * Fill the top form tokens of the template content.
 */
function fillSuisaHeadTokens(string $templateXml, array $show, ?array $venue): string
{
    $venue = $venue ?? [];

    return strtr($templateXml, [
        '{{VENUE_NAME}}' => suisaXmlEscape(trim((string) ($venue['name'] ?? ''))),
        '{{VENUE_STREET}}' => suisaXmlEscape(trim((string) ($venue['address'] ?? ''))),
        '{{VENUE_CITYLINE}}' => suisaXmlEscape(buildSuisaCityLine($venue)),
        '{{VENUE_CAPACITY}}' => suisaXmlEscape((string) ($venue['capacity'] ?? '')),
        '{{VENUE_TYPE}}' => suisaXmlEscape(trim((string) ($venue['type'] ?? ''))),
        '{{SHOW_DATE}}' => suisaXmlEscape(formatSuisaDate((string) ($show['show_date'] ?? ''))),
        '{{TEAM_NAME}}' => suisaXmlEscape(trim((string) ($show['team_name'] ?? ''))),
    ]);
}

/**
 * Build the filled SUISA form as ODT bytes.
 *
 * @param array $show row from team_shows plus team_name
 * @param array|null $venue row from venues (linked venue, may be missing)
 * @param array $songs setlist rows with name/composer/arranger/duration_seconds
 */
function buildSuisaOdt(array $show, ?array $venue, array $songs, ?string $templateDir = null): string
{
    $templateDir ??= dirname(__DIR__, 3) . '/assets/suisa_template';

    $entries = suisaTemplateEntries($templateDir);
    if (!isset($entries['content.xml'])) {
        throw new RuntimeException('SUISA template has no content.xml.');
    }

    $content = fillSuisaHeadTokens($entries['content.xml'], $show, $venue);
    $content = fillSuisaSongRows($content, $songs);

    if (strpos($content, '{{') !== false) {
        throw new RuntimeException('SUISA template contains unhandled tokens.');
    }

    $entries['content.xml'] = $content;

    return buildZipBytes($entries);
}
