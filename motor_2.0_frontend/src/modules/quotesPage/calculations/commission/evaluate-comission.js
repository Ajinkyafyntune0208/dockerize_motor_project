import { handleOperators } from "modules/quotesPage/calculations/commission/operator-evaluation";
import { calculateBrokerage } from "modules/quotesPage/calculations/commission/calculate-brokerage";
import _ from "lodash";

//Evealuate Commission
export const _evaluateCommission = (
  commission,
  totalPremiumA,
  totalPremiumC,
  totalPremiumB,
  totalPremium,
  totalAddon,
  finalPremium,
  KeyMapping
) => {

  const getBrokerage = (brokerage) => {
    const safeNumber = (number) => (number * 1 ? number * 1 : 0);
    let applicableOd =
      safeNumber(totalPremiumA) -
      safeNumber(totalPremiumC) +
      (brokerage?.totalOdPayable ? safeNumber(totalAddon) : 0);
      
    return {
      od: +calculateBrokerage(
        brokerage?.totalOdPayable || brokerage?.odPremium,
        applicableOd
      ),
      tp: +calculateBrokerage(brokerage?.tpPremium, totalPremiumB),
      net: +calculateBrokerage(brokerage?.netPremium, totalPremium),
      addon: +calculateBrokerage(brokerage?.addonPremium, totalAddon),
      gross: +calculateBrokerage(brokerage?.totalPremium, finalPremium),
    };
  };

  //evaluate standard brokerage
  const {
    od: standardOd,
    tp: standardTp,
    net: standardNet,
    addon: standardAddon,
    gross: standardFinalPayable,
  } = getBrokerage(commission?.brokerage) || {};

  //EVALUATE RULE QUALIFICATION
  const _calculateCustomBrokerage = (rules) => {
    let brokeragePremium = 0;
    rules.forEach(({ brokerage, sub_rule }) => {
      let qualificationMatrix = [];
      if (!_.isEmpty(sub_rule)) {
        sub_rule.forEach(({ field_slug, operator, value }) => {
          qualificationMatrix.push(
            handleOperators(field_slug, operator, value, KeyMapping)
          );
        });
      }
      if (
        !qualificationMatrix.includes(false) &&
        !_.isEmpty(qualificationMatrix)
      ) {
        //evaluate standard brokerage
        const {
          od: brokerageOd,
          tp: brokerageTp,
          net: brokerageNet,
          addon: brokerageAddon,
          gross: brokerageFinalPayable,
        } = getBrokerage(brokerage) || {};
        brokeragePremium =
          brokeragePremium +
          brokerageOd +
          brokerageTp +
          brokerageNet +
          brokerageAddon +
          brokerageFinalPayable;
      }
    });
    return brokeragePremium;
  };

  let standardBrokerage =
    standardOd +
    standardTp +
    standardNet +
    standardAddon +
    standardFinalPayable;

  return {
    standardBrokerage: standardBrokerage
      ? standardBrokerage.toFixed(2)
      : standardBrokerage,
    ...(!_.isEmpty(commission?.rules) && {
      customBrokerage: _calculateCustomBrokerage(commission?.rules)
        ? _calculateCustomBrokerage(commission?.rules).toFixed(2)
        : 0,
    }),
  };
};
