import React from "react";
import { Badge } from "react-bootstrap";
import { currencyFormater } from "utils";
import { useSelector } from "react-redux";
import Style from "../style";
import PropTypes from "prop-types";

export const InfoCardKnowMore = ({ quote, finalPremium, tab, revisedNcb }) => {
  const { temp_data } = useSelector((state) => state.home);
  //Storing NCB in renewal cases.
  const ApplicableNCB =
    temp_data?.corporateVehiclesQuoteRequest?.isClaim !== "Y" &&
    quote?.isRenewal === "Y"
      ? temp_data?.oldJourneyData?.oldNcb
      : quote?.ncbDiscount;

  return (
    <Style.FormLeftCont>
      <Style.FormLeftLogoNameWrap>
        <Style.FormLeftLogo>
          <img src={quote?.companyLogo} alt="plan logo" />
        </Style.FormLeftLogo>
        <Style.FormLeftNameWrap>
          <Style.FormLeftPlanName>{quote?.companyName}</Style.FormLeftPlanName>
        </Style.FormLeftNameWrap>
      </Style.FormLeftLogoNameWrap>

      <Style.FormLeftWrap>
        <Style.FormTermDataRow>
          <Style.FormleftTerm>
            <Style.FormleftTermTxt>Cover Value (IDV) </Style.FormleftTermTxt>
          </Style.FormleftTerm>
          <Style.FormRightTerm>
            <Style.FormleftTermAmount name="cover_value">
              {tab === "tab2" ? (
                <Badge
                  variant="secondary"
                  style={{
                    cursor: "pointer",
                  }}
                >
                  Not Applicable
                </Badge>
              ) : (
                ` ₹ ${currencyFormater(quote?.idv)}`
              )}
            </Style.FormleftTermAmount>
          </Style.FormRightTerm>
        </Style.FormTermDataRow>
        <Style.FormTermDataRow>
          <Style.FormleftTerm>
            <Style.FormleftTermTxt>
              New NCB {tab === "tab2" ? <></> : `(${quote?.ncbDiscount}%)`}{" "}
            </Style.FormleftTermTxt>
          </Style.FormleftTerm>
          <Style.FormRightTerm>
            <Style.FormleftTermAmount name="ncb">
              {tab === "tab2" ? (
                <Badge
                  variant="secondary"
                  style={{
                    cursor: "pointer",
                  }}
                >
                  Not Applicable
                </Badge>
              ) : (
                ` ₹ ${currencyFormater(revisedNcb)}`
              )}
            </Style.FormleftTermAmount>
          </Style.FormRightTerm>
        </Style.FormTermDataRow>
        <Style.FormTermDataRow>
          <Style.FormleftTerm>
            <Style.FormleftTermTxt>
              Final Premium
              <div>(Including GST)</div>
            </Style.FormleftTermTxt>
          </Style.FormleftTerm>
          <Style.FormRightTerm>
            <Style.FormleftTermAmount name="final_premium">
              ₹ {currencyFormater(finalPremium)}
            </Style.FormleftTermAmount>
          </Style.FormRightTerm>
        </Style.FormTermDataRow>
        {temp_data?.corporateVehiclesQuoteRequest?.selectedGvw &&
          quote?.company_alias === "reliance" ? (
            <Style.FormTermDataRow>
              <Style.FormleftTerm>
                <Style.FormleftTermTxt>
                  Gross Vehicle Weight <div>(lbs)</div>{" "}
                </Style.FormleftTermTxt>
              </Style.FormleftTerm>
              <Style.FormRightTerm>
                <Style.FormleftTermAmount>
                  {" "}
                  {temp_data?.selectedGvw}
                </Style.FormleftTermAmount>
              </Style.FormRightTerm>
            </Style.FormTermDataRow>
          ) : <noscript />
          }
      </Style.FormLeftWrap>
    </Style.FormLeftCont>
  );
};

export default InfoCardKnowMore;

InfoCardKnowMore.propTypes = {
  quote: PropTypes.object,
  finalPremium: PropTypes.number,
  tab: PropTypes.string,
  revisedNcb: PropTypes.number,
};
