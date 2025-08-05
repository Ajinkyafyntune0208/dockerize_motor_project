import _ from 'lodash';

export const calculateBrokerage = (data, premium) => {
    if (data && !_.isEmpty(data)) {
      let brokerageAmount = data?.amount;
      let brokerageType = data?.brokerage_type;
      if (brokerageType === "VARIABLE") {
        return (+premium * +brokerageAmount) / 100;
      }
      if (brokerageType === "FLAT") {
        return +brokerageAmount;
      }
    } else {
      return 0;
    }
  };