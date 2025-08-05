import _ from "lodash";
import { PreviousPolicyCondition } from "../form-section/proposal-logic";
import { currencyFormater } from "utils";

export const _proposalPdf = (
  temp,
  checkAddon,
  type,
  fields,
  Theme,
  enquiryId
) => {
  // pdf generation
  const Additional = !_.isEmpty(temp?.addons) ? temp?.addons : {};
  //Addons & accesories
  const addOnName = !_.isEmpty(checkAddon)
    ? checkAddon?.map(({ addon_name }) => addon_name)
    : [];

  const Accessories = !_.isEmpty(Additional?.accessories)
    ? _.compact(
        Additional?.accessories?.map((elem) => (elem?.sumInsured ? elem : null))
      )
    : [];

  const FilteredAccessories =
    !_.isEmpty(Accessories) && !_.isEmpty(addOnName)
      ? _.compact(
          Accessories?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];

  // * accessories list
  const accessories = _.compact([
    ...FilteredAccessories,
    temp?.selectedQuote?.addOnsData?.other &&
    !_.isEmpty(Object.keys(temp?.selectedQuote?.addOnsData?.other)) &&
    Object.keys(temp?.selectedQuote?.addOnsData?.other).includes("lLPaidDriver")
      ? { name: "LL Paid Driver" }
      : "",
  ]);

  const selectedAccessories = accessories.reduce(
    (result, { name, sumInsured }) => {
      if (name || sumInsured * 1) {
        result[name] = sumInsured * 1 ? `₹ ${Math.round(sumInsured)}` : "";
      }
      return result;
    },
    {}
  );

  const Discounts = !_.isEmpty(Additional?.discounts)
    ? Additional?.discounts
    : [];

  const FilteredDiscounts =
    !_.isEmpty(Discounts) && !_.isEmpty(addOnName)
      ? _.compact(
          Discounts?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];

  // * applicable addons list
  const applicableAddons = Additional?.applicableAddons?.reduce(
    (result, { name, premium, sumInsured }) => {
      if (name || premium * 1 || sumInsured * 1) {
        result[name] =
          premium * 1 || sumInsured * 1
            ? `₹ ${Math.round(premium || sumInsured)}`
            : "";
      }
      return result;
    },
    {}
  );

  const AdditionalCovers = !_.isEmpty(Additional?.additionalCovers)
    ? _.compact(
        Additional?.additionalCovers?.map((elem) =>
          elem?.sumInsured * 1 ||
          elem?.sumInsured * 1 === 0 ||
          elem?.premium * 1 ||
          elem?.premium * 1 === 0 ||
          elem?.name === "Geographical Extension"
            ? elem
            : elem?.lLNumberCleaner ||
              elem?.lLNumberConductor ||
              elem?.lLNumberDriver
            ? elem
            : null
        )
      )
    : [];

  const FilteredAdditionalCovers =
    !_.isEmpty(AdditionalCovers) && !_.isEmpty(addOnName)
      ? _.compact(
          AdditionalCovers?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];

  //CPA check
  const ReasonPA = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident?.filter(
        ({ reason }) => reason && reason !== "cpa not applicable to company"
      )
    : [];

  const PACondition =
    !_.isEmpty(ReasonPA) &&
    temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C" &&
    temp?.corporateVehiclesQuoteRequest?.policyType !== "own_damage"
      ? true
      : false;

  //previous policy details check
  const PolicyCon = PreviousPolicyCondition(temp);

  const CPA = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident
    : [];

  const FilteredCPA =
    !_.isEmpty(CPA) && !_.isEmpty(addOnName)
      ? _.compact(
          CPA?.map((item) => (addOnName.includes(item.name) ? item : null))
        )
      : [];

  const {
    address,
    addressLine1,
    city,
    cityId,
    dob,
    email,
    firstName,
    fullName,
    gender,
    genderName,
    gstNumber,
    isCkycDetailsRejected,
    isckycPresent,
    lastName,
    mobileNumber,
    occupation,
    occupationName,
    officeEmail,
    panNumber,
    pincode,
    prevOwnerType,
    state,
    stateId,
    ifsc,
    bankName,
    branchName,
    accountNumber,
    pepStatus,
    gogreenStatus,
  } = temp?.userProposal?.additonalData?.owner || {};

  const {
    nomineeAge,
    nomineeDob,
    nomineeName,
    nomineeRelationship,
    relationshipWithOwner,
  } = temp?.userProposal?.additonalData?.nominee || {};

  const {
    chassisNumber,
    engineNumber,
    regNo1,
    regNo2,
    regNo3,
    registrationDate,
    rtoLocation,
    vehicaleRegistrationNumber,
    vehicleColor,
    vehicleManfYear,
  } = temp?.userProposal?.additonalData?.vehicle || {};

  const {
    cPAInsComp,
    cPAPolicyFmDt,
    cPAPolicyNo,
    cPAPolicyToDt,
    cPASumInsured,
    cpaInsuranceCompany,
    cpaPolicyEndDate,
    cpaPolicyNumber,
    cpaPolicyStartDate,
    cpaSumInsured,
    reason,
    applicableNcb,
    insuranceCompanyName,
    isClaim,
    prevPolicyExpiryDate,
    previousInsuranceCompany,
    previousNcb,
    previousPolicyExpiryDate,
    previousPolicyNumber,
    tpInsuranceCompanyName,
    tpInsuranceNumber,
    tpStartDate,
    tpEndDate,
  } = temp?.userProposal?.additonalData?.prepolicy || {};
  const isIndividual =
    temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I";
  //pdf JSON
  return {
    data: {
      general_information: {
        ic_logo: temp?.selectedQuote?.companyLogo,
        enquiryId: temp?.journeyId,
        insurer_company: temp?.selectedQuote?.companyName || `N/A`,
        insurer_type: temp?.selectedQuote?.productName || `N/A`,
        ...(temp?.quoteLog?.premiumJson?.icAddress && {
          ic_address: temp?.quoteLog?.premiumJson?.icAddress,
        }),
        plan_and_policy_type:
          (temp?.selectedQuote?.policyType === "Comprehensive" &&
          type !== "cv" &&
          temp?.newCar
            ? "Bundled"
            : temp?.selectedQuote?.policyType || "N/A") +
          (temp?.selectedQuote?.policyType === "Short Term"
            ? ` (${
                temp?.selectedQuote?.premiumTypeCode === "short_term_3" ||
                temp?.selectedQuote?.premiumTypeCode === "short_term_3_breakin"
                  ? "3 Months"
                  : "6 Months"
              }) - Comprehensive`
            : temp?.selectedQuote?.policyType === "Comprehensive" &&
              temp?.newCar &&
              type !== "cv"
            ? ` - 1 yr. OD + ${type === "car" ? 3 : 5} yr. TP`
            : temp?.newCar && type !== "cv"
            ? ` - ${type === "car" ? 3 : 5} years`
            : ` - Annual`),

        idv: temp?.selectedQuote?.idv
          ? `₹ ${currencyFormater(temp?.selectedQuote?.idv)}`
          : `N/A`,
        vehicle_details: {
          manf_name: temp?.selectedQuote?.mmvDetail?.manfName || `N/A`,
          model_name: temp?.selectedQuote?.mmvDetail?.modelName || `N/A`,
          ...(temp?.selectedQuote?.mmvDetail?.versionName && {
            variant:
              `${temp?.selectedQuote?.mmvDetail?.versionName} ${
                type !== "bike" ? "-" : ""
              } ${
                type !== "bike"
                  ? temp?.parent?.productSubTypeCode === "GCV"
                    ? temp?.selectedQuote?.mmvDetail?.grossVehicleWeight ||
                      temp?.corporateVehiclesQuoteRequest?.defaultGvw ||
                      "N/A"
                    : temp?.selectedQuote?.mmvDetail?.cubicCapacity || "N/A"
                  : ""
              } ${
                type !== "bike"
                  ? temp?.parent?.productSubTypeCode === "GCV"
                    ? "gvw"
                    : "CC"
                  : ""
              }` || "N/A",
          }),
          ...(temp?.corporateVehiclesQuoteRequest?.selectedGvw &&
            temp?.corporateVehiclesQuoteRequest?.defaultGvw &&
            temp?.corporateVehiclesQuoteRequest?.selectedGvw * 1 !==
              temp?.corporateVehiclesQuoteRequest?.defaultGvw * 1 && {
              selected_gwv:
                `${temp?.corporateVehiclesQuoteRequest?.selectedGvw} lbs` ||
                `N/A`,
            }),
          ...(temp?.selectedQuote?.mmvDetail?.fuelType && {
            fuel_type: temp?.selectedQuote?.mmvDetail?.fuelType,
          }),
        },
        premium_breakup: {
          ...(temp?.quoteLog?.odPremium !== 0 &&
            temp?.quoteLog?.premiumJson?.isRenewal !== "Y" &&
            temp?.quoteLog?.premiumJson?.companyAlias !== "bajaj_allianz" && {
              own_damage_premium: `₹ ${
                currencyFormater(temp?.quoteLog?.odPremium) || `0`
              }`,
              third_party_premium: `₹ ${
                currencyFormater(
                  temp?.quoteLog?.tpPremium -
                    (temp?.quoteLog?.premiumJson?.tppdDiscount * 1 || 0)
                ) || `0`
              }`,
              addon_premium: `₹ ${
                currencyFormater(temp?.quoteLog?.addonPremium) || `0`
              }`,
            }),

          total_discount: {
            ncb_include:
              temp?.selectedQuote?.policyType !== "Third Party"
                ? `(NCB ${
                    temp?.corporateVehiclesQuoteRequest?.applicableNcb ||
                    temp?.quoteLog?.quoteDetails?.applicableNcb ||
                    `0`
                  }% Incl.)`
                : ``,
            final_discount: `- ₹ ${currencyFormater(
              temp?.selectedQuote?.finalTotalDiscount * 1
                ? temp?.selectedQuote?.finalTotalDiscount * 1
                : 0
            )} `,
          },
          gst: `₹ ${currencyFormater(temp?.quoteLog?.serviceTax) || `0`}`,
          total_premium_payable: `₹ ${
            currencyFormater(temp?.quoteLog?.finalPremiumAmount) || `0`
          }`,
        },
        ...(applicableAddons &&
          Object.keys(applicableAddons).length > 0 && {
            selected_addons: applicableAddons,
          }),
        ...(Object.keys(selectedAccessories).length > 0 && {
          selected_accessories: selectedAccessories,
        }),
        ...((!_.isEmpty(FilteredAdditionalCovers) ||
          (!_.isEmpty(FilteredCPA)
            ? FilteredCPA[0]?.name
              ? true
              : false
            : false)) && ({...(!_.isEmpty(FilteredAdditionalCovers) && {
          additional_covers: {
            ...FilteredAccessories.reduce((result, { name, sumInsured }) => {
              result[
                name.replace(/_/g, " ").split(" ").map(_.capitalize).join(" ")
              ] = sumInsured * 1 ? `₹ ${sumInsured}` : "";
              return result;
            }, {}),
          },
        })})),
        ...((!_.isEmpty(FilteredDiscounts) ||
          temp?.quoteLog?.premiumJson?.tppdDiscount * 1) && {
          discount: {
            ...FilteredAccessories.reduce((result, { name, sumInsured }) => {
              result[
                name === "voluntary_insurer_discounts"
                  ? "Voluntary Deductibles"
                  : name
                      .replace(/_/g, " ")
                      .split(" ")
                      .map(_.capitalize)
                      .join(" ")
              ] = sumInsured * 1 ? `₹ ${sumInsured}` : "";
              return result;
            }, {}),
            ...(temp?.quoteLog?.premiumJson?.tppdDiscount * 1 && {
              tppd_discount: `₹ ${temp?.quoteLog?.premiumJson?.tppdDiscount}`,
            }),
          },
        }),
      },
      vehicle_owner_details: {
        [`${isIndividual ? `dob` : `doi`}`]: dob,
        email: email,
        [`${isIndividual ? `first_name` : `company_name`}`]: firstName,
        ...(isIndividual && {
          full_name: fullName,
        }),
        gender: gender,
        gender_name: genderName,
        ...(gstNumber && { gstNumber: gstNumber }),
        is_ckyc_details_rejected: isCkycDetailsRejected,
        is_ckyc_present: isckycPresent,
        ...(lastName && {
          [`${isIndividual ? `last_name` : `representative_name`}`]: lastName,
        }),
        mobile_number: mobileNumber,
        occupation: occupation,
        occupation_name: occupationName,
        ...(officeEmail && { office_email: officeEmail }),
        ...(panNumber && { pan_number: panNumber }),
        ...(prevOwnerType && { prev_owner_type: prevOwnerType }),
        state_id: stateId,
        communication_address: {
          address: address,
          address_line_1: addressLine1,
          city: city,
          pincode: pincode,
          state: state,
          city_id: cityId,
        },
        bankDetails: {
          ifsc: ifsc,
          bankName: bankName,
          branchName: branchName,
          accountNumber: accountNumber,
          pepStatus: pepStatus,
          gogreenStatus: gogreenStatus,
        }
      },
      ...(nomineeName && {
        nominee_details: {
          nominee_name: nomineeName,
          nominee_age: nomineeAge,
          nominee_dob: nomineeDob,
          nominee_relationship: nomineeRelationship,
          relationship_with_owner: relationshipWithOwner,
        },
      }),
      vehicle_details: {
        chassis_number: chassisNumber,
        engine_number: engineNumber,
        ...(regNo1 && { reg_no_1: regNo1 }),
        ...(regNo2 && { reg_no_2: regNo2 }),
        ...(regNo3 && { reg_no_3: regNo3 }),
        registration_date: registrationDate,
        [`Registered RTO`]: rtoLocation,
        [`Registration Number`]: vehicaleRegistrationNumber,
        vehicle_color: vehicleColor,
        vehicle_manfacture_year: vehicleManfYear,
      },
      ...(temp?.userProposal?.isVehicleFinance === "1" && {
        financer_details: {
          name_of_financer: temp?.userProposal?.nameOfFinancer,
          financer_agreement_type: temp?.userProposal?.financerAgreementType,
          financer_location: temp?.userProposal?.financerLocation,
          hypothecation_city: temp?.userProposal?.hypothecationCity,
        },
      }),
      ...(temp?.userProposal?.isCarRegistrationAddressSame === "0" && {
        vehicle_registration_address: {
          ...(temp?.userProposal?.carRegistrationAddress1 && {
            car_registration_address1:
              temp?.userProposal?.carRegistrationAddress1,
          }),
          ...(temp?.userProposal?.carRegistrationAddress2 && {
            car_registration_address2:
              temp?.userProposal?.carRegistrationAddress2,
          }),
          ...(temp?.userProposal?.carRegistrationAddress3 && {
            car_registration_address3:
              temp?.userProposal?.carRegistrationAddress3,
          }),
          ...(temp?.userProposal?.carRegistrationCity && {
            car_registration_city: temp?.userProposal?.carRegistrationCity,
          }),
          ...(temp?.userProposal?.carRegistrationCityId && {
            car_registration_cityId: temp?.userProposal?.carRegistrationCityId,
          }),
          ...(temp?.userProposal?.carRegistrationPincode && {
            car_registration_pincode:
              temp?.userProposal?.carRegistrationPincode,
          }),
          ...(temp?.userProposal?.carRegistrationState && {
            car_registration_state: temp?.userProposal?.carRegistrationState,
          }),
          ...(temp?.userProposal?.carRegistrationStateId && {
            car_registration_stateId:
              temp?.userProposal?.carRegistrationStateId,
          }),
        },
      }),
      ...(PolicyCon && PACondition && fields?.includes("cpaOptOut")
        ? {
            previous_policy_details: {
              ...(cPAInsComp && { cpa_insurance_company_code: cPAInsComp }),
              ...(cPAPolicyFmDt && { cpa_policy_start_date: cPAPolicyFmDt }),
              ...(cPAPolicyNo && { cpa_policy_no: cPAPolicyNo }),
              ...(cPAPolicyToDt && { cpa_policy_end_date: cPAPolicyToDt }),
              ...(cPASumInsured && { cpa_suminsured: cPASumInsured }),
              ...(cpaInsuranceCompany && {
                cpa_insurance_company: cpaInsuranceCompany,
              }),
              ...(cpaPolicyEndDate && { cpaPolicyEndDate: cpaPolicyEndDate }),
              ...(cpaPolicyNumber && { cpaPolicyNumber: cpaPolicyNumber }),
              ...(cpaPolicyStartDate && {
                cpaPolicyStartDate: cpaPolicyStartDate,
              }),
              ...(cpaSumInsured && { cpaSumInsured: cpaSumInsured }),
              ...(reason && { [`Opt-out reason`]: reason }),
              ...(applicableNcb && { applicableNcb: applicableNcb }),
              ...(insuranceCompanyName && {
                insuranceCompanyName: insuranceCompanyName,
              }),
              ...(isClaim && { [`Claim in previous policy`]: isClaim === "Y" ? "Yes" : "No"}),
              ...(prevPolicyExpiryDate && {
                prevPolicyExpiryDate: prevPolicyExpiryDate,
              }),
              ...(previousInsuranceCompany && {
                previousInsuranceCompany: previousInsuranceCompany,
              }),
              ...(previousNcb && { previousNcb: previousNcb }),
              ...(previousPolicyExpiryDate && {
                previousPolicyExpiryDate: previousPolicyExpiryDate,
              }),
              ...(previousPolicyNumber && {
                previousPolicyNumber: previousPolicyNumber,
              }),
            },
          }
        : PolicyCon
        ? {
            policy_details: {
              ...(applicableNcb && { applicableNcb: applicableNcb }),
              ...(insuranceCompanyName && {
                insuranceCompanyName: insuranceCompanyName,
              }),
              ...(isClaim && { isClaim: isClaim }),
              ...(prevPolicyExpiryDate && {
                prevPolicyExpiryDate: prevPolicyExpiryDate,
              }),
              ...(previousInsuranceCompany && {
                previousInsuranceCompany: previousInsuranceCompany,
              }),
              ...(previousNcb && { previousNcb: previousNcb }),
              ...(previousPolicyExpiryDate && {
                previousPolicyExpiryDate: previousPolicyExpiryDate,
              }),
              ...(previousPolicyNumber && {
                previousPolicyNumber: previousPolicyNumber,
              }),
            },
          }
        : {
            ...(cpaInsuranceCompany && {
              cpa_details: {
                ...(cPAInsComp && { cPAInsComp: cPAInsComp }),
                ...(cPAPolicyFmDt && { cPAPolicyFmDt: cPAPolicyFmDt }),
                ...(cPAPolicyNo && { cPAPolicyNo: cPAPolicyNo }),
                ...(cPAPolicyToDt && { cPAPolicyToDt: cPAPolicyToDt }),
                ...(cPASumInsured && { cPASumInsured: cPASumInsured }),
                ...(cpaInsuranceCompany && {
                  cpaInsuranceCompany: cpaInsuranceCompany,
                }),
                ...(cpaPolicyEndDate && { cpaPolicyEndDate: cpaPolicyEndDate }),
                ...(cpaPolicyNumber && { cpaPolicyNumber: cpaPolicyNumber }),
                ...(cpaPolicyStartDate && {
                  cpaPolicyStartDate: cpaPolicyStartDate,
                }),
                ...(cpaSumInsured && { cpaSumInsured: cpaSumInsured }),
                ...(reason && { reason: reason }),
              },
            }),
            ...(tpInsuranceCompanyName && {
              tp_details: {
                ...(tpInsuranceCompanyName && {
                  tp_insurance_company_name: tpInsuranceCompanyName,
                }),
                ...(tpInsuranceNumber && {
                  tp_insurance_number: tpInsuranceNumber,
                }),
                ...(tpStartDate && { tp_start_date: tpStartDate }),
                ...(tpEndDate && { tp_end_date: tpEndDate }),
              },
            }),
          }),
    },
    broker_theme_color: Theme?.leadPageBtn?.background1 || "rgb(189, 212, 0)",
    broker_text_color: Theme?.leadPageBtn?.textColor || "rgb(0, 0, 0)",
  };
};
