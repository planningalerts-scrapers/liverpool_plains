require 'scraperwiki'
require 'horizon_xml'

collector = Horizon_xml.new
collector.setPeriod(ENV['MORPH_PERIOD']).setDomain('horizondap_lpsc').setInfoUrl(collector.host_url).setCommentUrl('mailto:lpsc@lpsc.nsw.gov.au')

collector.getRecords.each do |record|
#   p record
  if (ScraperWiki.select("* from data where `council_reference`='#{record['council_reference']}'").empty? rescue true)
    puts "Saving record " + record['council_reference'] + ", " + record['address']
    ScraperWiki.save_sqlite(['council_reference'], record)
  else
    puts "Skipping already saved record " + record['council_reference']
  end
end
