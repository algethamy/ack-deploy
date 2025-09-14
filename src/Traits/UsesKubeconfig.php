<?php

namespace Algethamy\LaravelAckDeploy\Traits;

trait UsesKubeconfig
{
    /**
     * Get kubectl command with appropriate kubeconfig
     */
    protected function getKubectlCommand(array $baseCommand): array
    {
        $kubeconfigPath = base_path('kubeconfig.yaml');

        if (file_exists($kubeconfigPath)) {
            // Insert --kubeconfig flag after kubectl
            array_splice($baseCommand, 1, 0, ['--kubeconfig', $kubeconfigPath]);
        }

        return $baseCommand;
    }

    /**
     * Get kubeconfig path (local if exists, otherwise global)
     */
    protected function getKubeconfigPath(): ?string
    {
        $localPath = base_path('kubeconfig.yaml');

        if (file_exists($localPath)) {
            return $localPath;
        }

        $globalPath = $_SERVER['HOME'] . '/.kube/config';

        if (file_exists($globalPath)) {
            return $globalPath;
        }

        return null;
    }

    /**
     * Check if kubeconfig is available (local or global)
     */
    protected function hasKubeconfig(): bool
    {
        return $this->getKubeconfigPath() !== null;
    }
}