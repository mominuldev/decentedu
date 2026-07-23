import { api } from '@/lib/api';

export type ReportFormat = 'pdf' | 'excel';

interface ArtifactStatus {
    id: number;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    error_message: string | null;
    download_url: string | null;
}

function triggerDownload(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

async function pollArtifact(artifactId: number): Promise<ArtifactStatus> {
    for (let attempt = 0; attempt < 30; attempt++) {
        const res = await api.get(`/api/v1/reports/artifacts/${artifactId}`);
        const artifact = res.data.data as ArtifactStatus;
        if (artifact.status === 'ready' || artifact.status === 'failed') return artifact;
        await new Promise((resolve) => setTimeout(resolve, 1500));
    }
    throw new Error('Report generation timed out.');
}

/**
 * Downloads a report as PDF/Excel. Small/interactive reports stream back inline; reports the
 * server flags as queued come back as a 202 + artifact id, which this polls until ready.
 */
export async function downloadReport(key: string, format: ReportFormat, params: Record<string, unknown> = {}): Promise<void> {
    const extension = format === 'pdf' ? 'pdf' : 'xlsx';
    const res = await api.get(`/api/v1/reports/${key}/${format}`, { params, responseType: 'blob' });

    if ((res.headers['content-type'] as string | undefined)?.includes('application/json')) {
        const parsed = JSON.parse(await (res.data as Blob).text());
        const artifact = await pollArtifact(parsed.data.artifact_id);
        if (artifact.status === 'failed' || !artifact.download_url) {
            throw new Error(artifact.error_message ?? 'Report generation failed.');
        }
        const fileRes = await api.get(artifact.download_url, { responseType: 'blob' });
        triggerDownload(fileRes.data, `${key}.${extension}`);
        return;
    }

    triggerDownload(res.data, `${key}.${extension}`);
}
