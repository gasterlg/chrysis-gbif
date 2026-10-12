<p>Everything we know about one species from GBIF: its GBIF name, the records imported and a map.</p>
<h3>The GBIF panel</h3>
<ul>
    <li>Shows the GBIF name and key our species is linked to, and whether GBIF treats it as accepted or as a synonym.</li>
    <li>If the link looks wrong, fix it in <strong>Match review</strong>.</li>
</ul>
<h3>Import button</h3>
<ol>
    <li>Press the green <strong>Import from GBIF</strong> button at the <strong>top right</strong> of this page to fetch this species’ records. It runs in the background; the page shows progress. (To import many species at once, use the Species list.)</li>
    <li>New or changed records arrive as <strong>pending</strong> – they are not public yet.</li>
    <li>Review them in <strong>Import review</strong> (the link on this page opens it filtered to this species).</li>
</ol>
@include('help._import-where')
<h3>The map</h3>
<ul>
    <li>Each dot is a GBIF record with coordinates. Click a dot for locality, date, collector and dataset.</li>
    <li>Dots far from the rest are often errors (wrong coordinates or misidentification) – they are flagged for review.</li>
</ul>
