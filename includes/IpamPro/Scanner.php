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

		$target = $subnet['subnet'].'/'.$subnet['cidr'];

		// Multi-technique host discovery — no root privileges required.
		//   -sn   : ping scan, don't probe ports (discovery only)
		//   -PE   : ICMP echo request (classic ping)
		//   -PP   : ICMP timestamp request (catches hosts that block echo)
		//   -PS   : TCP SYN probe on common ports (catches hosts that
		//           block ICMP entirely but still answer on open ports)
		//   -PA   : TCP ACK probe on common ports (catches hosts behind
		//           stateless firewalls that only block SYN)
		//   --send-ip : force IP-layer probes instead of raw Ethernet/ARP,
		//           which avoids needing CAP_NET_RAW for ARP scanning
		//   -T4   : aggressive timing — fast without sacrificing accuracy
		//   -n    : skip reverse-DNS lookups (faster, avoids DNS timeouts)
		// This combination catches hosts that would be missed by a plain
		// ICMP-only ping sweep (firewalled servers, Windows hosts with
		// ICMP disabled, etc.) without requiring -O/-sS which need root.
		$command = $this->command('nmap', [
			'-sn',
			'-PE', '-PP', '-PS21,22,23,25,80,135,139,443,445,3389,8080',
			'-PA80,443',
			'--send-ip',
			'-T4',
			'-n',
			$target,
		]);
		$output  = $this->run($command);

		$responding = $this->parseIps($output['text']);
		$hasResults = count($responding) > 0;
		$status     = ($output['exit_code'] === 0 || $hasResults) ? 'completed' : 'failed';
		$message    = $status === 'completed'
			? 'nmap discovery scan completed — '.count($responding).' host(s) found.'
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
	 *
	 * Multi-probe discovery scans (-PE -PP -PS -PA) are still slower
	 * than a single-technique ping sweep on large subnets, so we raise
	 * PHP's execution time limit for the duration of the scan only.
	 */
	public function run(string $command): array {
		$previousLimit = ini_get('max_execution_time');
		set_time_limit(180); // allow up to 3 minutes for large subnets

		$lines     = [];
		$exit_code = 1;
		@exec($command, $lines, $exit_code);

		set_time_limit((int) $previousLimit);

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
	 * Works for -sn discovery output regardless of which probe
	 * technique (-PE/-PP/-PS/-PA) actually got the response — nmap
	 * still emits exactly one "Nmap scan report for" line per host
	 * that answered any of the probes.
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
