<?php
declare(strict_types = 1);

namespace Modules\IPAMPro\Includes\IpamPro;

final class Scanner {
	private Repository $repository;

	public function __construct(Repository $repository) {
		$this->repository = $repository;
	}

	public function scan(int $subnetid): array {
		$subnet = $this->repository->subnet($subnetid);
		if (!$subnet) {
			throw new \RuntimeException('Subnet was not found.');
		}

		$target  = $subnet['subnet'].'/'.$subnet['cidr'];
		$command = $this->command('nmap', ['-sn', $target]);
		$output  = $this->run($command);

		// nmap exits 0 on success; treat anything else as failed unless we
		// still got "Nmap scan report" lines (root vs non-root differences).
		$responding = $this->parseIps($output['text']);
		$hasResults = count($responding) > 0;
		$status     = ($output['exit_code'] === 0 || $hasResults) ? 'completed' : 'failed';
		$message    = $status === 'completed'
			? 'nmap scan completed — '.count($responding).' responding host(s).'
			: 'nmap error: '.trim($output['text']);

		$counts = $this->repository->markScanResult($subnetid, $responding);
		$this->repository->recordScan($subnetid, $command, $status, $counts, $message);

		return [
			'status'     => $status,
			'message'    => $message,
			'responding' => $responding,
			'counts'     => $counts,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function command(string $binary, array $args): string {
		$parts = [escapeshellcmd($binary)];
		foreach ($args as $arg) {
			$parts[] = escapeshellarg($arg);
		}
		return implode(' ', $parts).' 2>&1';
	}

	/**
	 * Execute a shell command and return its output and exit code.
	 * Kept as a separate method so it can be overridden in tests.
	 */
	public function run(string $command): array {
		$lines     = [];
		$exit_code = 1;
		@exec($command, $lines, $exit_code);

		return [
			'exit_code' => (int) $exit_code,
			'lines'     => $lines,
			'text'      => implode("\n", $lines),
		];
	}

	/**
	 * Extract only IPs from nmap "Nmap scan report for <ip>" lines.
	 *
	 * nmap output looks like:
	 *   Nmap scan report for 192.168.1.1
	 *   Nmap scan report for router.local (192.168.1.254)
	 *
	 * We intentionally ignore every other line (summary, MAC, latency, etc.)
	 * to avoid false positives that caused every IP to appear as "used".
	 */
	private function parseIps(string $text): array {
		$ips = [];

		// Match bare IP:  "Nmap scan report for 192.168.1.1"
		// Match FQDN+IP:  "Nmap scan report for hostname (192.168.1.1)"
		preg_match_all(
			'/^Nmap scan report for (?:\S+ \()?([0-9]{1,3}(?:\.[0-9]{1,3}){3})\)?$/m',
			$text,
			$matches
		);

		foreach ($matches[1] as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
				$ips[$ip] = true;
			}
		}

		return array_keys($ips);
	}
}
