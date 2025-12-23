# APP step 1 
 - 1° Implementação de camada (cache) usando Memcache 
 - 2° Criando novos plugins 
 - 3° Ajustando time de request, e volumetria de requests externas 
 - 4° Ajuste de (SDN (NSX)) RX/TX sem dados visiveis 
 - 5° Criado step 2 
 --- 
 # APP step 2 
 - 1° 
 - 2° 
 - 3° 
  
 --- 
 # Problema: Escala 
     -- NSX Manager:  
 - Crie um NSX Collector 
 - Plugin SDN (NSX) vira apenas LEITOR 
 Vamos ajustar o plugin para evitar um grave aumento na latência na API dos NSX, para isso vamos implemantar o que defini acima! Para evitar de sobrecarregar as APIs! 
 - Não vamos mudar nada das Dashboards, nem nos dados coletados! apenas o jeito que é coletado. 
 - Resolvido: Criando Service e camada Cache com service.php e worker 
 --- 
 # GeoMap: 
     -- Criar Cyber Map em Tempo Real 
 - Netflow API 
 - IPINFO 
 - SNMP 
 - NSX 
 - Cloudflare Radar 
 - Collector/service cache  
 - API KEY 
 - 903316a03b56a0b4db74226acba9ec4f 
  
  
  
 - Verificar IPs Ativos: Consultar em lote os três blocos de IPs para identificar quais IPs estão ativos na rede. 
  
 - Geolocalização com IPinfo: Para cada IP ativo, usar o IPinfo para mapear a localização geográfica e criar um mapa visual na opção “Maps”. 
  
 - Análise de Vulnerabilidades com Shodan: Verificar as portas abertas e vulnerabilidades nos IPs ativos, usando o Shodan. 
  
 - Validação com AbuseIPDB: Para os IPs que se conectam aos seus IPs externos, consultar o AbuseIPDB para verificar se há alguma reputação maliciosa. 
  
 - Criação do Mapa: Consolidar todas essas informações em um único mapa interativo, mostrando a geolocalização, as vulnerabilidades e a reputação dos IPs.  
  
  
 Agora no Painel lateral, em Maps - Vamos refatorar completamente o que tem ali! Visto que o que atualmente tem não se encaixa na solicitação! deve ser criado um mapa semelhantemente ao do Print Screen aqui definido! usando os efeitos definidos na legenda: 
  
 - Verde: meus IPs 
 - Vermelho: IPs que estão no AbuseIPDB 
 - Amarelo: IPs que estão com portas vuneráveis e expostas via shodan. 
 - Cada IP deve ser representado no mapa por um ponto, cintilante e pulsante. 
  
  
 PORTAL - KEY 
  
 IPflow 
 Client ID 
 D2skCl7ixtUnPML9SMFYBQqnLwNzHy14v8psmR0oubSjqptG 
 Client Secret 
 zbb7bGqneInxR6sVrNOLyCTvDIk02xMQDgoSnWRmbHBOCcME6qp7lSYki0WZj32OvbrClQqzGfIEQBNAucdI31PWbXuyLftSHiDLqn4suEcOn81DtMkLdcAtGVvq3DPi 
  
 Person ID: 8ABBD3DF0DA5EE52AB965E7F11439FB9  
  
  
  
 --- 
 Correlação completa, pronta para uso! apenas ativando as APIs. 
  
 - AbuseIPDB 
 - IPinfo 
 - Shodan 
 - Netflow 
 - Deepflow 
 - Network (BGP) - AS, Blocos de IPs 
 - SNMP 
 - FortiAnalyzer 
 - Cloudflare Radar 
 - Nuclei 
 - Wazuh 
 - `https://github.com/IndexOffy/tor-network-api`  
 - Corgea CVE API -> `https://docs.corgea.app/api-reference/introduction`  
 - Corgea token: f669897b-0187-40c0-98be-148e8039c60b 
  
  
 Todas essas ferramentas devem ser para Correlação de eventos para o mapa, mesmo que estejam desativadas nos plugins, devem estar prontas para uso, com apenas um clique! 
  
 - Sendo assim todas essas fontes de dados para generation de eventos devem estar funcionais! 
 - Valide cada uma delas! 
 - todas devem estar funcionando perfeitamente! Da seguinte forma: 
     -- Network (BGP) - AS, Blocos de IPs -> fonte de todas as (vitimas) os ips e AS, a terem visibilidade garantida no mapa! 
     -- Use a verificação dos blocos de IPs em lotes, validando com IPINFO eles. 
     -- Use o AbuseIPDB para analisar o score de IPs, que atacam os blocos de ips! Corelacionando! 
     -- Use o Shodan para analisar a superficie de ataque, validando portas, e protocolos vulneráveis! 
     -- Use o SNMP como fonte de dados vindo direto dos equipamentos a serem defendidos, sejam: routes, switchs, etc.. 
     -- Use o Nuclei e o Wazuh para correlação de eventos! criando assim uma forte e resiliente sincronia entre eventos, mapeando ataques reais, e não apenas ruídos! 
     -- Use o FortiAnalyzer como uma grande fonte de dados, seja dos logs, incidentes, event monitor, mitre, interfaces: wan, sd-wan, lans, mpls, etc.. todas! Use como fonte de dados e correlação! 
     -- Tudo isso deve estar no mapa! O mapa como está atualmente, em cor, estilo e designer deve ser mantido, apenas vamos alimentar ele com mais dados!  
     -- Quero que use o `https://bgp.he.net/`  para: mapear os BGP PEER que se conectam ou tem algum tipo de conexão em qualquer camada com o AS definido nas configuações! exemplo: AS: meuas1212434, tem peer com o AS1213434, com o AS235657, e com o AS564656 -> com geolocalização, informações, e nomes de provedores, etc... isso tudo dentro de uma grande corelação de dados, bem como: topologia de rede: MPLS, BGP, SNMP, FLOW vivas!  
  
  
  
 # CVE  
     -- Agora usando a API do Corgea CVE vamos mapear os CVEs! 
     -- Mais um nível de corelação: CVE! 
     -- Usando a API Corgea vamos criar uma correlação entre as ferramentas que já estão em uso, para que possamos saber qual é a CVE, para assim saber como proceder! 
     - Corgea CVE API -> `https://docs.corgea.app/api-reference/introduction`  
     - Corgea token: f669897b-0187-40c0-98be-148e8039c60b 
     - Documentação: `https://docs.corgea.app/api-reference/authentication/verify-token`  
     - Tipos: curl --request GET \ 
   --url `https://www.corgea.app/api/v1/verify`  \ 
   --header 'CORGEA-TOKEN: <api-key>' 
   -- Lista de CVEs: `https://hub.corgea.com/threats`  
  
  
 # Traffic Locality 
 - INTERNAL: 
     -- Agora via SNMP vamos via discovery, medir o total de tráfego interno (entrada) de todos os IPs calculados, 
     E definir no valor apontado no Primeiro Print! 
 - EXTERNAL: 
     -- Agora via SNMP vamos via discovery, medir o total de tráfego externo (saída) no de todos os IPs calculados, 
     E definir no valor apontado no Segundo Print! 
  
  
 # Detalhe importante: 
     -- Cada Ataque, deve abrir um chamado com categoria Redes/Segurança 
     -- No chamado deve conter: CVE, Origem/Destino do ataque, o Score do Abuse, a descrição do tipo do ataque! 
     -- Quando um Peer BGP conectado direto com o MEU AS, abrir um chamado de nível baixo, para o time de redes, indicando QUAL é o AS que teve peer caindo.