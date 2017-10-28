require 'scraperwiki'
require 'horizon_xml'

collector = Horizon_xml.new
collector.base_url    = 'http://myhorizon.solorient.com.au/Horizon/'
collector.domain      = 'horizondap_lpsc'
collector.comment_url = 'mailto:lpsc@lpsc.nsw.gov.au'
collector.period      = ENV['MORPH_PERIOD']

collector.getRecords.each do |record|
#   p record
  if (ScraperWiki.select("* from data where `council_reference`='#{record['council_reference']}'").empty? rescue true)
    puts "Saving record " + record['council_reference'] + ", " + record['address']
    ScraperWiki.save_sqlite(['council_reference'], record)
  else
    puts "Skipping already saved record " + record['council_reference']
  end
end
